<?php

namespace IOL\Shop\v1\Entity;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use IOL\Shop\v1\Content\PDF;
use IOL\Shop\v1\DataSource\Database;
use IOL\Shop\v1\DataSource\Environment;
use IOL\Shop\v1\DataSource\File;
use IOL\Shop\v1\DataType\Date;
use IOL\Shop\v1\DataType\UUID;
use IOL\Shop\v1\Exceptions\InvalidValueException;
use IOL\Shop\v1\Exceptions\NotFoundException;

class Ticket
{
    public const DB_TABLE = 'tickets';

    private string $id;
    private Order $order;
    private string $userId;
    private Date $created;

    public function __construct(?string $id = null)
    {
        if (!is_null($id)) {
            if (!UUID::isValid($id)) {
                throw new InvalidValueException('Invalid Ticket ID');
            }
            $this->loadData(Database::getRow('id', $id, self::DB_TABLE));
        }
    }

    /**
     * @throws NotFoundException
     * @throws InvalidValueException
     */
    private function loadData(array|false $values): void
    {

        if (!$values || count($values) === 0) {
            throw new NotFoundException('Ticket could not be loaded');
        }

        $this->id = $values['id'];
        $this->order = new Order($values['order_id']);
        $this->userId = $values['user_id'];
        $this->created = new Date($values['created']);
    }

    public function createNew(Order $order): void
    {
        $this->id = UUID::newId(self::DB_TABLE);
        $this->order = $order;
        $this->userId = $order->getUserId();
        $this->created = new Date('u');

        $database = Database::getInstance();
        $database->insert(self::DB_TABLE, [
            'id'            => $this->id,
            'order_id'      => $this->order->getId(),
            'user_id'       => $this->userId,
            'created'       => $this->created->format(Date::DATETIME_FORMAT_MICRO),
        ]);
    }

    public function generatePDF()
    {
        //$user = new User($this->order->getUserid(), true);

        /* INITIALIZATION */
        $pdf = new PDF('PRINT@HOME');


        /* PERSONAL DATA (TOP LEFT) */
        $pdf->setFont('changa-bold', 'B', 8 * 1.4);
        $pdf->setXY(14, 32);
        $pdf->Cell(50, 7.5, 'Buchungsdaten', $pdf->borders, 0, 'L');


        //$address = $user->getAddressArray();

        $pdf->setFont('changa-bold', '', 8 * 1.4);

        $pdf->TextCell(14, 40, 100, 5,'[USERNAME]');
        $pdf->TextCell(14, 45, 100, 5,'[GENDER]');
        $pdf->TextCell(14, 50, 100, 5,'[NAME]');
        $pdf->TextCell(14, 55, 100, 5,'[ADDRESS]');
        $pdf->TextCell(14, 60, 100, 5,'[ZIP/CITY]');


        /* TICKET DATA (TOP RIGHT) */


        $pdf->setFont('changa', '', 8 * 1.4);
        $pdf->TextCell(80, 45, 30, 5,'Kaufdatum:', 'R');
        $pdf->TextCell(80, 50, 30, 5,'Ticket-ID:', 'R');
        $pdf->TextCell(80, 55, 30, 5,'Bestellnummer:', 'R');
        $pdf->TextCell(80, 60, 30, 5,'Zahlart:', 'R');

        $paymentMethod = $this->order->getPaymentMethod()->getPrettyValue();

        $pdf->TextCell(115, 45, 80, 5, $this->order->getCreated()->format("d.m.Y"));
        $pdf->TextCell(115, 50, 80, 5, $this->id);
        $pdf->TextCell(115, 55, 80, 5, $this->order->getId());
        $pdf->TextCell(115, 60, 80, 5, $paymentMethod);



        /* TICKETDATA */
        $pdf->setFont('changa-bold', 'B', 15 * 1.4);
        $pdf->TextCell(15, 80, 150, 15, 'Isle of LAN - "Honored" 2022');

        $pdf->setFont('changa', '', 8 * 1.4);
        $pdf->TextCell(15, 95, 70, 5, 'Auholzsaal');
        $pdf->TextCell(15, 100, 70, 5, 'Kapellenstrasse 14');
        $pdf->TextCell(15, 105, 70, 5, '8583 Sulgen');



        $pdf->TextCell(15, 130, 60, 5, 'Ticketpreis');
        $pdf->setFont('changa-bold', 'B', 15 * 1.4);
        $pdf->TextCell(15, 139, 60, 6, 'CHF 50.00');



        $pdf->setFont('changa-bold', 'B', 8 * 1.4);
        $pdf->TextCell(75, 125, 40, 5, 'Türöffnung');

        $pdf->setFont('changa', 'B', 8 * 1.4);
        $pdf->TextCell(75, 130, 40, 5, 'Freitag');
        $pdf->setFont('changa', '', 8 * 1.4);
        $pdf->TextCell(75, 135, 40, 5, '08.04.2022');
        $pdf->TextCell(75, 140, 40, 5, '17:00 Uhr');


        $pdf->TextCell(115, 135, 35, 5, 'bis');


        $pdf->setFont('changa', 'B', 8 * 1.4);
        $pdf->TextCell(130, 130, 40, 5, 'Sonntag');
        $pdf->setFont('changa', '', 8 * 1.4);
        $pdf->TextCell(130, 135, 40, 5, '10.04.2022');
        $pdf->TextCell(130, 140, 40, 5, '13:00 Uhr');


        $writer = new PngWriter();
        $qrCode = QrCode::create($this->id)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(new ErrorCorrectionLevelMedium())
            ->setSize(300)
            ->setMargin(10)
            ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));


        $logo = Logo::create(File::getBasePath().'/assets/images/iol-qr-logo.png')
            ->setResizeToWidth(100);

        $qrPath = Environment::get('GENERATED_CONTENT_PATH') .'/qr/ticket-'.$this->id.'.png';
        $result = $writer->write($qrCode, $logo);
        $result->saveToFile($qrPath);
        $pdf->Image($qrPath, 155, 80, 40, 40);

        /* BOTTOM PART */
        /*
                    $this->pdf->setXY(15, 155);
                    $this->pdf->MultiCell(85, 5, utf8_decode('Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy'), $pdf->borders, 'L');
                    $this->pdf->setXY(110, 155);
                    $this->pdf->MultiCell(85, 5, utf8_decode('Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy'), $pdf->borders, 'L');
        */
        $pdf->setXY(15, 155);

        $pdf->setFont('changa', '', 8 * 1.4);
        $pdf->MultiCell(180, 5, utf8_decode('Vielen Dank für deinen Ticketkauf. Damit du weisst, was dich beim Check-In erwartet und was du tun musst, um Zugriff auf das Netzwerk zu erhalten, haben wir dir dies unten aufgelistet!'), $pdf->borders, 'L');

        $pdf->setXY(15, 170);
        $pdf->MultiCell(85, 5, utf8_decode("1. Drucke dieses Ticket aus und nimm es mit zum Check-In. Lass vorerst bitte alles im Auto, wenn du zum Check-In kommst.\r\n\r\n2. Als erstes müssen wir von dir ein COVID-Zertifikat (2G+) sehen. Dieses wird dann überprüft. Halte hierzu bitte das Zertifikat und einen Ausweis bereit.\r\n\r\n"), $pdf->borders, 'L');
        $pdf->setXY(110, 170);
        $pdf->MultiCell(85, 5, utf8_decode("3. Anschliessend wird am Check-In-Schalter wird dein Ticket überprüft. Wenn alles stimmt, erhältst du dein Eintrittsband, welches an deinem Handgelenk festgemacht wird. Bitte lass es während der Isle of LAN immer an, du benötigst es auch, um dir etwas am Kiosk zu kaufen.\r\n\r\n4. Suche den Platz, den du reserviert hast, bringe anschliessend dein Material dort hin und richte dich ein. Wenn du Hilfe brauchst, zögere nicht, eine Person mit einem Staff T-Shirt anzusprechen."), $pdf->borders, 'L');


        $pdf->SetLineWidth(0.5);
        $pdf->SetDash(.5, 1);
        $pdf->Line(10, 75, 200, 75);
        $pdf->Line(10, 150, 200, 150);





        $filename = Environment::get('GENERATED_CONTENT_PATH') . '/tickets/ticket-'.$this->id.'.pdf';

        /* OUTPUT */
        $pdf->Output("F", $filename);
        return $filename;
    }
}