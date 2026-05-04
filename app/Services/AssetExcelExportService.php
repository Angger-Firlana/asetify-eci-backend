<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class AssetExcelExportService
{
    private const BASE_HEADERS = [
        'Asset ID',
        'Serial Number',
        'Asset Category ID',
        'Asset Category',
        'Brand ID',
        'Brand',
        'Model Name',
        'Source Location ID',
        'Source Location',
        'Current Location ID',
        'Current Location',
        'Condition Status',
        'Notes',
        'Created By',
        'Updated By',
        'Created At',
        'Updated At',
        'Photo Count',
        'Primary Photo URL',
        'Photo URLs',
    ];

    private PhotoUploadService $photoUploadService;

    public function __construct(?PhotoUploadService $photoUploadService = null)
    {
        $this->photoUploadService = $photoUploadService ?? new PhotoUploadService();
    }

    public function build(array $assets, array $photosByAsset, bool $includeImages = true): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive extension is required to export Excel files.');
        }

        $maxPhotoCount = 0;
        if ($includeImages) {
            foreach ($photosByAsset as $photos) {
                $maxPhotoCount = max($maxPhotoCount, count($photos));
            }
        }

        $headers = self::BASE_HEADERS;
        for ($index = 1; $index <= $maxPhotoCount; $index++) {
            $headers[] = 'Photo ' . $index;
        }

        $rows             = [];
        $rowHeights       = [];
        $drawingAnchors   = [];
        $drawingRelations = [];
        $mediaFiles       = [];
        $contentTypes     = [];
        $mediaIndex       = 0;
        $photoColumnStart = count(self::BASE_HEADERS) + 1;

        foreach ($assets as $assetIndex => $asset) {
            $sheetRow    = $assetIndex + 2;
            $assetId     = (int) ($asset['id'] ?? 0);
            $assetPhotos = $photosByAsset[$assetId] ?? [];
            $photoUrls   = array_map(
                static fn (array $photo): string => site_url('api/v1/assets/' . $assetId . '/download-photo/' . $photo['id']),
                $assetPhotos
            );

            $rows[] = [
                (string) $assetId,
                (string) ($asset['serial_number'] ?? ''),
                (string) ($asset['asset_category_id'] ?? ''),
                (string) ($asset['asset_category_name'] ?? ''),
                (string) ($asset['brand_id'] ?? ''),
                (string) ($asset['brand_name'] ?? ''),
                (string) ($asset['model_name'] ?? ''),
                (string) ($asset['source_location_id'] ?? ''),
                (string) ($asset['source_location_name'] ?? ''),
                (string) ($asset['current_location_id'] ?? ''),
                (string) ($asset['current_location_name'] ?? ''),
                (string) ($asset['condition_status'] ?? ''),
                (string) ($asset['notes'] ?? ''),
                (string) ($asset['created_by'] ?? ''),
                (string) ($asset['updated_by'] ?? ''),
                (string) ($asset['created_at'] ?? ''),
                (string) ($asset['updated_at'] ?? ''),
                (string) count($assetPhotos),
                $photoUrls[0] ?? '',
                implode("\n", $photoUrls),
            ];

            if (! $includeImages) {
                continue;
            }

            foreach (array_values($assetPhotos) as $photoIndex => $photo) {
                $embeddedImage = $this->loadEmbeddableImage($photo);
                if ($embeddedImage === null) {
                    continue;
                }

                $mediaIndex++;
                $mediaPath = 'xl/media/image' . $mediaIndex . '.' . $embeddedImage['extension'];
                $mediaFiles[$mediaPath] = $embeddedImage['contents'];
                $contentTypes[$embeddedImage['extension']] = $embeddedImage['content_type'];

                $relationshipId = 'rId' . $mediaIndex;
                $drawingRelations[] = [
                    'id' => $relationshipId,
                    'target' => '../media/' . basename($mediaPath),
                ];
                $drawingAnchors[] = [
                    'relationship_id' => $relationshipId,
                    'name' => $photo['file_name'] ?? ('Photo ' . $mediaIndex),
                    'column' => $photoColumnStart + $photoIndex,
                    'row' => $sheetRow,
                    'shape_id' => $mediaIndex,
                ];
                $rowHeights[$sheetRow] = 80;
            }
        }

        $hasDrawing = $includeImages && $drawingAnchors !== [];
        $files = [
            '[Content_Types].xml' => $this->buildContentTypesXml($contentTypes, $hasDrawing),
            '_rels/.rels' => $this->buildRootRelationshipsXml(),
            'docProps/app.xml' => $this->buildAppPropertiesXml(),
            'docProps/core.xml' => $this->buildCorePropertiesXml(),
            'xl/workbook.xml' => $this->buildWorkbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->buildWorkbookRelationshipsXml(),
            'xl/styles.xml' => $this->buildStylesXml(),
            'xl/worksheets/sheet1.xml' => $this->buildSheetXml($headers, $rows, $rowHeights, $hasDrawing),
        ];

        if ($hasDrawing) {
            $files['xl/worksheets/_rels/sheet1.xml.rels'] = $this->buildWorksheetRelationshipsXml();
            $files['xl/drawings/drawing1.xml'] = $this->buildDrawingXml($drawingAnchors);
            $files['xl/drawings/_rels/drawing1.xml.rels'] = $this->buildDrawingRelationshipsXml($drawingRelations);
            foreach ($mediaFiles as $path => $contents) {
                $files[$path] = $contents;
            }
        }

        return $this->zipFiles($files);
    }

    private function loadEmbeddableImage(array $photo): ?array
    {
        $path = $this->photoUploadService->absolutePath((string) ($photo['file_path'] ?? ''));
        if (! is_file($path)) {
            return null;
        }

        $extension = strtolower((string) ($photo['extension'] ?? pathinfo($path, PATHINFO_EXTENSION)));
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $supportedExtensions = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
        ];

        if (isset($supportedExtensions[$extension])) {
            return [
                'contents' => $contents,
                'extension' => $extension,
                'content_type' => $supportedExtensions[$extension],
            ];
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagepng')) {
            return null;
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return null;
        }

        ob_start();
        imagepng($image);
        $pngContents = ob_get_clean();
        imagedestroy($image);

        if ($pngContents === false) {
            return null;
        }

        return [
            'contents' => $pngContents,
            'extension' => 'png',
            'content_type' => 'image/png',
        ];
    }

    private function zipFiles(array $files): string
    {
        $tempPath = tempnam(WRITEPATH . 'cache', 'asset-export-');
        if ($tempPath === false) {
            throw new RuntimeException('Failed to prepare temporary export file.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($tempPath);
            throw new RuntimeException('Failed to create Excel archive.');
        }

        foreach ($files as $path => $contents) {
            $zip->addFromString($path, $contents);
        }

        $zip->close();

        $binary = @file_get_contents($tempPath);
        @unlink($tempPath);

        if ($binary === false) {
            throw new RuntimeException('Failed to read generated Excel archive.');
        }

        return $binary;
    }

    private function buildSheetXml(array $headers, array $rows, array $rowHeights, bool $hasDrawing): string
    {
        $totalRows  = count($rows) + 1;
        $lastColumn = $this->columnLetter(count($headers));
        $dimension  = 'A1:' . $lastColumn . max(1, $totalRows);

        $columnsXml = '';
        foreach ($headers as $index => $header) {
            $columnNumber = $index + 1;
            $width = str_starts_with($header, 'Photo ') ? 18 : match ($header) {
                'Notes', 'Photo URLs' => 40,
                'Primary Photo URL' => 30,
                default => 18,
            };

            $columnsXml .= sprintf(
                '<col min="%1$d" max="%1$d" width="%2$s" customWidth="1"/>',
                $columnNumber,
                $this->xmlNumber($width)
            );
        }

        $sheetRowsXml = $this->buildSheetRowXml(1, $headers, true);
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $sheetRowsXml .= $this->buildSheetRowXml($rowNumber, $row, false, $rowHeights[$rowNumber] ?? null);
        }

        $drawingXml = $hasDrawing ? '<drawing r:id="rId1"/>' : '';

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <dimension ref="{$dimension}"/>
  <sheetViews>
    <sheetView workbookViewId="0">
      <pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>
    </sheetView>
  </sheetViews>
  <sheetFormatPr defaultRowHeight="15"/>
  <cols>{$columnsXml}</cols>
  <sheetData>{$sheetRowsXml}</sheetData>
  {$drawingXml}
  <pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>
</worksheet>
XML;
    }

    private function buildSheetRowXml(int $rowNumber, array $values, bool $isHeader, ?float $height = null): string
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $column  = $this->columnLetter($index + 1);
            $cellRef = $column . $rowNumber;
            $style   = $isHeader ? ' s="1"' : '';
            $cells .= sprintf(
                '<c r="%s" t="inlineStr"%s><is><t%s>%s</t></is></c>',
                $cellRef,
                $style,
                $this->requiresPreserveSpace($value) ? ' xml:space="preserve"' : '',
                $this->escapeInlineString($value)
            );
        }

        $attributes = ' r="' . $rowNumber . '"';
        if ($height !== null) {
            $attributes .= ' ht="' . $this->xmlNumber($height) . '" customHeight="1"';
        }

        return '<row' . $attributes . '>' . $cells . '</row>';
    }

    private function buildContentTypesXml(array $imageContentTypes, bool $hasDrawing): string
    {
        $imageDefaults = '';
        foreach ($imageContentTypes as $extension => $contentType) {
            $imageDefaults .= sprintf(
                '<Default Extension="%s" ContentType="%s"/>',
                $extension,
                $contentType
            );
        }

        $drawingOverride = $hasDrawing
            ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>'
            : '';

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  {$imageDefaults}
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  {$drawingOverride}
</Types>
XML;
    }

    private function buildRootRelationshipsXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
    }

    private function buildWorkbookXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Assets" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private function buildWorkbookRelationshipsXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function buildStylesXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font>
      <sz val="11"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
    <font>
      <b/>
      <sz val="11"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
  </cellXfs>
  <cellStyles count="1">
    <cellStyle name="Normal" xfId="0" builtinId="0"/>
  </cellStyles>
</styleSheet>
XML;
    }

    private function buildWorksheetRelationshipsXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>
</Relationships>
XML;
    }

    private function buildDrawingXml(array $anchors): string
    {
        $picturesXml = '';
        foreach ($anchors as $anchor) {
            $fromColumn = $anchor['column'] - 1;
            $toColumn   = $anchor['column'];
            $fromRow    = $anchor['row'] - 1;
            $toRow      = $anchor['row'];
            $name       = $this->escapeAttribute((string) $anchor['name']);
            $shapeId    = (int) $anchor['shape_id'];
            $relationshipId = $anchor['relationship_id'];

            $picturesXml .= <<<XML
  <xdr:twoCellAnchor editAs="oneCell">
    <xdr:from>
      <xdr:col>{$fromColumn}</xdr:col>
      <xdr:colOff>9525</xdr:colOff>
      <xdr:row>{$fromRow}</xdr:row>
      <xdr:rowOff>9525</xdr:rowOff>
    </xdr:from>
    <xdr:to>
      <xdr:col>{$toColumn}</xdr:col>
      <xdr:colOff>0</xdr:colOff>
      <xdr:row>{$toRow}</xdr:row>
      <xdr:rowOff>0</xdr:rowOff>
    </xdr:to>
    <xdr:pic>
      <xdr:nvPicPr>
        <xdr:cNvPr id="{$shapeId}" name="{$name}"/>
        <xdr:cNvPicPr/>
      </xdr:nvPicPr>
      <xdr:blipFill>
        <a:blip r:embed="{$relationshipId}"/>
        <a:stretch><a:fillRect/></a:stretch>
      </xdr:blipFill>
      <xdr:spPr>
        <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
      </xdr:spPr>
    </xdr:pic>
    <xdr:clientData/>
  </xdr:twoCellAnchor>
XML;
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
{$picturesXml}
</xdr:wsDr>
XML;
    }

    private function buildDrawingRelationshipsXml(array $relationships): string
    {
        $itemsXml = '';
        foreach ($relationships as $relationship) {
            $itemsXml .= sprintf(
                '<Relationship Id="%s" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="%s"/>',
                $relationship['id'],
                $relationship['target']
            );
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  {$itemsXml}
</Relationships>
XML;
    }

    private function buildAppPropertiesXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Asetify</Application>
</Properties>
XML;
    }

    private function buildCorePropertiesXml(): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:creator>Asetify</dc:creator>
  <cp:lastModifiedBy>Asetify</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:modified>
</cp:coreProperties>
XML;
    }

    private function columnLetter(int $index): string
    {
        $column = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $column = chr(65 + $mod) . $column;
            $index = intdiv($index - 1, 26);
        }

        return $column;
    }

    private function escapeInlineString(string $value): string
    {
        $value = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u', '', $value) ?? $value;

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function requiresPreserveSpace(string $value): bool
    {
        return $value !== trim($value) || str_contains($value, "\n");
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function xmlNumber(float|int $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
