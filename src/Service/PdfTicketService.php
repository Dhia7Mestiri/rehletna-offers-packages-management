<?php

namespace App\Service;

use App\Entity\Reservation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfTicketService
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    public function generate(Reservation $reservation): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);

        $html = $this->twig->render('reservation/ticket.html.twig', [
            'reservation' => $reservation,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function generateToFile(Reservation $reservation): string
    {
        $dir  = dirname(__DIR__, 2) . '/var/tickets/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir . 'ticket_' . $reservation->getId() . '_' . time() . '.pdf';
        file_put_contents($path, $this->generate($reservation));

        return $path;
    }
}