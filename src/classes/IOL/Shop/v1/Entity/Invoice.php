<?php

namespace IOL\Shop\v1\Entity;

use IOL\Shop\v1\Content\PDF;
use IOL\Shop\v1\DataSource\Database;
use IOL\Shop\v1\DataSource\Environment;
use IOL\Shop\v1\DataSource\File;
use IOL\Shop\v1\DataType\Date;
use IOL\Shop\v1\DataType\UUID;
use IOL\Shop\v1\Enums\Gender;
use IOL\Shop\v1\Exceptions\InvalidValueException;
use IOL\Shop\v1\Exceptions\NotFoundException;

class Invoice
{
    public const DB_TABLE = 'invoices';

    private string $id;
    private Order $order;
    private Date $created;
    private string $externalId;
    private int $value;
    private string $number;
    private const ESR_TN = "010077020";
    private const ESR_ID = "334761";
    private const ESR_QR = "CH7430808003746612927";

    /**
     * @throws NotFoundException
     * @throws InvalidValueException
     */
    public function __construct(?string $id = null, ?int $number = null)
    {
        if (!is_null($id)) {
            if (!UUID::isValid($id)) {
                throw new InvalidValueException('Invalid Invoice ID');
            }
            $this->loadData(Database::getRow('id', $id, self::DB_TABLE));
        }
        if (!is_null($number)) {
            $this->loadData(Database::getRow('number', $number, self::DB_TABLE));
        }
    }

    /**
     * @throws NotFoundException|InvalidValueException
     */
    public function getForOrder(Order $order)
    {
        $database = Database::getInstance();
        $database->where('order_id', $order->getId());
        $row = $database->get(self::DB_TABLE, 1);
        $this->loadData($row[0]);
    }

    public function isFullyPayed(): bool
    {
        return $this->getTotalPayed() >= $this->getValue();
    }

    public function getAllPayments(): array
    {
        $database = Database::getInstance();
        $database->where('invoice_id', $this->id);

        $payments = [];
        foreach($database->get(Payment::DB_TABLE) as $paymentData){
            $payment = new Payment();
            $payment->loadData($paymentData);
            $payments[] = $payment;
        }
        return $payments;
    }

    public function getTotalPayed(): int
    {
        $total = 0;
        /** @var Payment $payment */
        foreach($this->getAllPayments() as $payment){
            $total += $payment->getValue();
        }
        return $total;
    }

    /**
     * @throws NotFoundException
     * @throws InvalidValueException
     */
    private function loadData(array|false $values): void
    {

        if (!$values || count($values) === 0) {
            throw new NotFoundException('Invoice could not be loaded');
        }

        $this->id = $values['id'];
        $this->order = new Order($values['order_id']);
        $this->created = new Date($values['created']);
        $this->externalId = $values['external_id'];
        $this->value = $values['value'];
        $this->number = $values['number'];
    }

    public function createNew(Order $order, string $externalId): void
    {
        $this->id = UUID::newId(self::DB_TABLE);
        $this->order = $order;
        $this->created = new Date('u');
        $this->externalId = $externalId;
        $this->value = $this->order->getTotal();
        $this->number = $this->generateInvoiceNumber();

        $database = Database::getInstance();
        $database->insert(self::DB_TABLE, [
            'id' => $this->id,
            'order_id' => $this->order->getId(),
            'created' => $this->created->format(Date::DATETIME_FORMAT_MICRO),
            'external_id' => $this->externalId,
            'value' => $this->value,
            'number' => $this->number
        ]);
    }

    public function generateInvoiceNumber(): string
    {
        return str_pad(substr(str_replace('.', '', microtime(true)), 2), 12,"0");
    }

    /**
     * @return int
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }


    public function createPayment(int $value)
    {
        $payment = new Payment();
        $payment->createNew($this, $value);
    }

    public function generatePDF(): string
    {
        /* INITIALIZATION */
        $pdf = new PDF('Rechnung #'.$this->number);
        $pdf->Image(File::getBasePath() . '/assets/images/esr_hq.png', 0, 191, 210, 106);


        /* HEADER */
        $pdf->setFont('changa-bold', 'B', 8 * 1.4);
        $pdf->TextCell(145, 15, 50, 5, 'Isle of LAN', 'R');

        $pdf->setFont('changa', '', 8 * 1.4);
        $pdf->TextCell(145, 20, 50, 5, '8574 Illighausen', 'R');

        $pdf->setFont('changa-bold', 'B', 8 * 1.4);
        $pdf->TextCell(145, 30, 50, 5, 'Fragen?', 'R');
        $pdf->setFont('changa', '', 8 * 1.4);
        $pdf->TextCell(145, 35, 50, 5, 'support@isleoflan.ch', 'R');

        $pdf->setFont('changa-bold', 'B', 8 * 1.4);
        $pdf->TextCell(145, 45, 50, 5, 'IBAN:', 'R');
        $pdf->setFont('changa', '', 8 * 1.4);
        $pdf->TextCell(145, 50, 50, 5, 'CH46 8080 8003 7466 1292 7', 'R');

        $pdf->setXY(15, 50);
        $pdf->MultiCell(50, 5, utf8_decode(implode("\r\n", [
            $this->order->userData['forename'] . ' ' . $this->order->userData['lastname'],
            $this->order->userData['address'],
            $this->order->userData['zipCode'] . ' ' . $this->order->userData['city']
        ])), $pdf->borders, 'L');


        $pdf->setXY(15, 80);
        $pdf->MultiCell(180, 5, utf8_decode(
            $this->order->userData['gender'] == Gender::COMPANY ?
            "" :
            "Vielen Dank für deine Bestellung! Wir bitten um eine Einzahlung des Rechnungsbetrags innert 20 Tagen. Falls du Fragen hast, melde dich gerne per E-Mail."
        ), $pdf->borders, 'L');


        /* CART */
        $pdf->setFont('changa-bold', 'B', 6 * 1.4);
        $pdf->TextCell(16.5, 95, 10, 5, 'Anz.');
        $pdf->TextCell(26.5, 95, 150, 5, 'Artikel / Beschreibung');
        $pdf->TextCell(165, 95, 300, 5, 'Preis');

        $pdf->setDrawColor(150, 150, 150);
        $pdf->Line(15, 100, 195, 100);

        $colored = false;
        $y = 100;
        $elementHeight = 10;
        $hideDescription = false;
        if (count($this->order->getItems()) > 4) {
            $elementHeight = 5;
            $hideDescription = true;
        }

        /** @var OrderItem $item */
        foreach ($this->order->getItems() as $item) {
            $pdf->setFillColor($colored ? 239 : 255, $colored ? 239 : 255, $colored ? 239 : 255);
            $y += $hideDescription ? 0 : ($elementHeight / 20);
            $pdf->TextCell(15, $y, 180, $elementHeight, '', 'L', true);

            $pdf->setFont('changa', '', 7 * 1.4);
            $pdf->TextCell(16.5, $y, 10, $elementHeight, (in_array($item->getProduct()->getCategory()->getId(),[3,999]) ? 1 : $item->getAmount()).'x', 'L', true);

            $pdf->setFont('changa-bold', 'B', 7 * 1.4);
            $pdf->TextCell(26.5, $y, 167, ($hideDescription ? $elementHeight : $elementHeight / 2), $item->getProduct()->getPaymentTitle(), 'L', true);


            if (!$hideDescription) {
                $y += ($elementHeight / 20 * 8);
                $pdf->setFont('changa', '', 6 * 1.4);
                $pdf->TextCell(26.5, $y, 167, $elementHeight / 2, $item->getProduct()->getPaymentDescription(), 'L', true);
                $y -= ($elementHeight / 20 * 8);
            }

            $pdf->setFont('changa', '', 7 * 1.4);
            $pdf->TextCell(165, $y, 10, $elementHeight, 'CHF', 'L', true);
            $pdf->TextCell(175, $y, 18.5, $elementHeight, number_format((in_array($item->getProduct()->getCategory()->getId(), [3,999]) ? $item->getPrice() : $item->getProduct()->getPrice()) / 100,2,'.',"'"),  'R', true);

            $y += $elementHeight;
            $colored = !$colored;
        }
        /* PAYMENT FEES */


        /* TOTAL */
        $pdf->setDrawColor(0, 0, 0);
        $pdf->setFillColor(255, 255, 255);
        $pdf->TextCell(15, $y, 180, 10, '', 'L', true);
        $pdf->setFont('changa-bold', 'B', 7 * 1.4);

        $pdf->TextCell(16.5, $y, 177, 10, 'TOTAL', 'L', true);
        $pdf->TextCell(165, $y, 10, 10, 'CHF', 'L', true);
        $pdf->TextCell(175, $y, 18.5, 10, number_format(($this->order->getTotal() / 100), 2, ".", "'"), 'R', true);

        $pdf->Line(15, $y, 195, $y);
        $y += 10;
        $pdf->Line(15, $y, 195, $y);


        /* TWINT */
        if($this->order->userData['gender'] != Gender::COMPANY) {
            $pdf->setFillColor(239, 239, 239);
            $pdf->TextCell(15, 155, 180, 30, '', 'L', true);
            $pdf->Image(File::getBasePath() . '/assets/images/twint.png', 20, 160, 20, 20);

            $pdf->setFont('changa-bold', 'B', 8 * 1.4);
            $pdf->TextCell(45, 160, 145, 5, 'Bezahle deine Rechnung mit TWINT');
            $pdf->setFont('changa', '', 6 * 1.4);
            $pdf->TextCell(45, 165, 145, 5, 'Du kannst diese Rechnung ganz beqeuem mit TWINT bezahlen. Sende hierzu einfach eine Zahlung an:');

            $pdf->setFont('changa-bold', 'B', 6 * 1.4);
            $pdf->TextCell(45, 171, 30, 5, 'Telefonnummer:');
            $pdf->TextCell(75, 171, 30, 5, 'Empfänger:');
            $pdf->TextCell(105, 171, 30, 5, 'Betrag:');
            $pdf->TextCell(135, 171, 30, 5, 'Nachricht:');

            $pdf->setFont('changa', '', 6 * 1.4);
            $pdf->TextCell(45, 175, 30, 5, '076 688 33 84');
            $pdf->TextCell(75, 175, 30, 5, 'Isle of LAN');
            $pdf->TextCell(105, 175, 30, 5, utf8_decode("CHF " . number_format($this->order->getTotal() / 100, 2, ".", "'")));
            $pdf->TextCell(135, 175, 30, 5, $this->number);
        }


        $rowHeight = 25.4 / 6;
        $columnWidth = 25.4 / 10;
        $pOffset = 1.5;

        //if($this->print_mode == 'print'){
        //    $x_offset = -1.1;
        //    $z_offset = 0;
        //    $y_offest = -1.2;
        //} else {
        $xOffset = 0;
        $zOffset = -1.1;
        $yOffest = 0;
        //}


        $total = $this->order->getTotal() / 100;
        $total = number_format($total, 2, ".", "");


        for ($x = $columnWidth; $x < $columnWidth * 26; $x += (24 * $columnWidth)) {
            $pdf->setFont('OpenSans', '', 7);
            $pdf->setXY($x + $pOffset, 191 + (2 * $rowHeight));
            $pdf->MultiCell(22 * $columnWidth, 3.5, "Raiffeisenbank Mittelthurgau\r\n8570 Weinfelden", $pdf->borders);

            $pdf->setXY($x + $pOffset, 191 + (5 * $rowHeight));
            $pdf->MultiCell(22 * $columnWidth, 3.5, "Isle of LAN\r\n8574 Illighausen", $pdf->borders);

            $pdf->setFont('OpenSans', 'B', 7);
            $pdf->setXY($x + (10 * $columnWidth), 191 + (10.1 * $rowHeight));
            $pdf->Cell(12 * $columnWidth, $rowHeight, $this->getNiceAccount(), $pdf->borders);


            $pdf->setFont('ocrb10bt', '', 12);
            $pdf->setXY($x, 191 + (12 * $rowHeight));
            $pdf->Cell(15 * $columnWidth, $rowHeight, floor($total), 0, 0, "R");

            $pdf->setXY($x + (18 * $columnWidth), 191 + (12 * $rowHeight));
            $pdf->Cell(3 * $columnWidth, $rowHeight, substr($total, -2), 0, 0, "L");
            $pOffset = 0;
        }


        $pdf->setFont('ocrb10bt', '', 12);
        $pdf->setXY(49 * $columnWidth, 191 + (12 * $rowHeight));
        $pdf->MultiCell(30 * $columnWidth, $rowHeight, utf8_decode(implode("\r\n", [
            $this->order->userData['forename'] . ' ' . $this->order->userData['lastname'],
            $this->order->userData['address'],
            $this->order->userData['zipCode'] . ' ' . $this->order->userData['city']
        ])), 0, "L");


        $pdf->setFont('ocrb10bt', '', 8);
        $pdf->setXY($columnWidth + 1.5, 191 + (14 * $rowHeight));
        $pdf->MultiCell(22 * $columnWidth, $rowHeight, $this->getNiceReference(false), $pdf->borders);


        $pdf->setFont('ocrb10bt', '', 12);
        $pdf->setXY($columnWidth + 1.5, 191 + (15 * $rowHeight));
        $pdf->MultiCell(22 * $columnWidth, $rowHeight, utf8_decode(implode("\r\n", [
            $this->order->userData['forename'] . ' ' . $this->order->userData['lastname'],
            $this->order->userData['address'],
            $this->order->userData['zipCode'] . ' ' . $this->order->userData['city']
        ])), $pdf->borders);


        /* CODIERZEILE */

        $x = 210 - (4 * $columnWidth);
        $y = 297 - ($rowHeight * 5);
        foreach (array_reverse(str_split($this->generateESRCode())) as $char) {
            $pdf->setXY($x + $xOffset, $y + $yOffest);
            //	$pdf->setXY($x, $y);
            $pdf->Cell($columnWidth, $rowHeight, $char, 0, 0, 'C');
            $x -= $columnWidth;
        }

        $x = 210 - (2 * $columnWidth) - $zOffset;
        $y = 297 - ($rowHeight * 17);
        foreach (array_reverse(str_split($this->getNiceReference())) as $char) {
            $pdf->setXY($x, $y);
            $pdf->Cell($columnWidth, $rowHeight, $char, 0, 0, 'C');
            $x -= $columnWidth;
        }


        $filename = Environment::get('GENERATED_CONTENT_PATH') . '/invoices/invoice-'.$this->id.'.pdf';

        /* OUTPUT */
        $pdf->Output("F", $filename);
        return $filename;
    }

    public function getNiceAccount(): string
    {
        $ret = '';
        $tmp = str_split(self::ESR_TN);
        $ret .= array_shift($tmp);
        $ret .= array_shift($tmp);
        $ret .= "-";

        $last = array_pop($tmp);
        $numberStarted = false;
        foreach ($tmp as $char) {
            if ($char == 0 && !$numberStarted) {
                continue;
            } else {
                $numberStarted = true;
                $ret .= $char;
            }
        }
        $ret .= "-";
        $ret .= $last;
        return $ret;
    }

    public function getNiceReference($withSpace = true): string
    {
        $r = str_split(implode("", array_reverse(str_split($this->getESRReference()))), 5);
        $return = '';
        foreach ($r as $part) {
            $return = implode("", array_reverse(str_split($part))) . ($withSpace ? ' ' : '') . $return;
        }
        return $return;
    }

    public function getESRReference(): string
    {
    $referenceBlock  = self::ESR_ID;
    $referenceBlock .= str_pad($this->number, 20, "0", STR_PAD_LEFT);
    $referenceBlock .= $this->getModulo($referenceBlock);

    return $referenceBlock;
}

    public function generateESRCode(): string
    {
        $esrCode = '';

        // BELEGART
        $totalBlock = "01"; //01 => ESR / 04 => ESR+

        // BETRAG
        $totalBlock .= str_pad(($this->order->getTotal()),10,"0", STR_PAD_LEFT);

        // PRÜFZIFFER
        $totalBlock .= $this->getModulo($totalBlock);

        // TRENNZEICHEN
        $esrCode .= $totalBlock;
        $esrCode .= ">";

        // REFERENZNUMMER
        $referenceBlock = $this->getEsrReference();
        $esrCode .= $referenceBlock;
        $esrCode .= "+ ";


        $esrCode .= self::ESR_TN;

        $esrCode .= ">";

        return $esrCode;
    }

    public function getModulo($input): int
    {
        $mod_template = array(0,9,4,6,8,2,7,1,3,5);
        $mod = array();
        for($i = 0; $i < 10; $i++){
            $mod[] = $mod_template;
            $next = array_shift($mod_template);
            $mod_template[] = $next;
        }

        $additional = 0;

        foreach(str_split($input) as $char){
            $additional = $mod[$additional][$char];
        }

        return (10-$additional)%10;
    }

    /**
     * @return string
     */
    public function getNumber(): string
    {
        return $this->number;
    }

    /**
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }


}