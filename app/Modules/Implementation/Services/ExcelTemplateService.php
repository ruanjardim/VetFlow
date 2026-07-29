<?php

namespace App\Modules\Implementation\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class ExcelTemplateService
{
    /**
     * @param  array<int, string>  $columns
     */
    public function write(array $columns, string $worksheetName): void
    {
        $writer = new Writer;
        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontColor(Color::DARK_BLUE)
            ->setBackgroundColor(Color::rgb(219, 234, 254));

        try {
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setName($worksheetName);
            $writer->addRow(Row::fromValues($columns, $headerStyle));
        } finally {
            $writer->close();
        }
    }
}
