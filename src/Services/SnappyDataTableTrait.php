<?php

namespace Yajra\Datatables\Services;

/**
 * Class SnappyDataTableTrait
 *
 * @package Yajra\Datatables\Services
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
trait SnappyDataTableTrait
{
    /**
     * PDF version of the table using print preview blade template.
     *
     * @return mixed
     */
    public function pdf()
    {
        $data   = $this->getDataForPrint();
        $snappy = app('snappy.pdf.wrapper');
        $snappy->setOptions([
            'no-outline'    => true,
            'margin-left'   => '0',
            'margin-right'  => '0',
            'margin-top'    => '10mm',
            'margin-bottom' => '10mm',
        ])->setOrientation('landscape');

        return $snappy->loadView($this->printPreview, compact('data'))
                      ->download($this->getFilename() . ".pdf");
    }
}