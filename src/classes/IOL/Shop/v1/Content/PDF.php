<?php

namespace IOL\Shop\v1\Content;

use IOL\Shop\v1\DataSource\File;

class PDF extends \FPDF
{
    public int $borders = 0;

    public function __construct(string $title)
    {
        if(!defined('FPDF_FONTPATH') || FPDF_FONTPATH != File::getBasePath().'/assets/fonts/'){
            define('FPDF_FONTPATH', File::getBasePath().'/assets/fonts/');
        }

        parent::__construct();
        $this->setAutoPageBreak(false);
        $this->addFont('changa-light', '', 'Changa-ExtraLight.php');
        $this->addFont('changa-light', 'B', 'Changa-Light.php');
        $this->addFont('changa', '', 'Changa-Regular.php');
        $this->addFont('changa', 'B', 'Changa-Medium.php');
        $this->addFont('changa-bold', '', 'Changa-SemiBold.php');
        $this->addFont('changa-bold', 'B', 'Changa-Bold.php');
        $this->addFont('changa-extrabold', '', 'Changa-ExtraBold.php');
        $this->addFont('OpenSans', '', 'OpenSans-Regular.php');
        $this->addFont('OpenSans', 'B', 'OpenSans-Bold.php');
        $this->addFont('ocrb10bt', '', 'ocrb10bt.php');

        /* HEADER */
        $this->addPage();
        $this->Image(File::getBasePath().'/assets/images/iol_black.jpg', 15, 15, 25, 12);

        $this->setFont('changa-bold', 'B', 15 * 1.4);
        $this->setXY(50, 15);
        $this->Cell(50, 12, $title, $this->borders, 0, 'L');

        if($this->borders == 1) {
            $this->Line(15, 0, 15, 300);
            $this->Line(150, 0, 150, 75);
            $this->Line(155, 0, 155, 75);
            $this->Line(210 - 15, 0, 210 - 15, 300);
        }
    }

    public function TextCell($x, $y, $w, $h, $text, $align = 'L', $fill = false)
    {
        $this->setXY($x, $y);
        $this->Cell($w, $h, utf8_decode($text), $this->borders, 0, $align, $fill);
    }

    public function SetDash($black=null, $white=null)
    {
        if($black!==null)
            $s=sprintf('[%.3F %.3F] 0 d',$black*$this->k,$white*$this->k);
        else
            $s='[] 0 d';
        $this->_out($s);
    }
}