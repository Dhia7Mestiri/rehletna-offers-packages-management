<?php

namespace App\Service;

use App\Entity\Reservation;
use MailerSend\MailerSend;
use MailerSend\Helpers\Builder\EmailParams;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\Helpers\Builder\Attachment;

class MailerSendService
{
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;

    public function __construct(string $apiKey, string $fromEmail, string $fromName)
    {
        $this->apiKey    = $apiKey;
        $this->fromEmail = $fromEmail;
        $this->fromName  = $fromName;
    }

    public function sendReservationConfirmation(Reservation $reservation, ?string $pdfPath = null): void
    {
        $mailerSend = new MailerSend(['api_key' => $this->apiKey]);

        $user  = $reservation->getUser();
        $offer = $reservation->getOffer();

        $recipients = [new Recipient($user->getEmail(), $user->getFullName())];

        $emailParams = (new EmailParams())
            ->setFrom($this->fromEmail)
            ->setFromName($this->fromName)
            ->setRecipients($recipients)
            ->setSubject('✅ Booking Confirmed — ' . $offer->getTitle())
            ->setHtml($this->buildEmailHtml($reservation))
            ->setText($this->buildEmailText($reservation));

        // Attach PDF ticket if provided
        if ($pdfPath && file_exists($pdfPath)) {
            $attachments = [
                new Attachment(
                    base64_encode(file_get_contents($pdfPath)),
                    'ticket_reservation_' . $reservation->getId() . '.pdf'
                )
            ];
            $emailParams->setAttachments($attachments);
        }

        $mailerSend->email->send($emailParams);

        // Clean up temp file
        if ($pdfPath && file_exists($pdfPath)) {
            unlink($pdfPath);
        }
    }

    private function buildEmailHtml(Reservation $reservation): string
    {
        $user  = $reservation->getUser();
        $offer = $reservation->getOffer();

        $specialRequest = $reservation->getSpecialRequest()
            ? '<tr><td style="padding:8px 0;color:#6b8282;font-size:14px">Special Requests</td>
               <td style="padding:8px 0;font-weight:600;color:#0d1f1e;text-align:right">'
               . htmlspecialchars($reservation->getSpecialRequest()) . '</td></tr>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f3;font-family:'Segoe UI',Arial,sans-serif;">
  <div style="max-width:600px;margin:32px auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(13,110,99,0.10);">

    <div style="background:linear-gradient(135deg,#1dbcaa,#1b3a6b);padding:36px 32px;text-align:center;">
      <div style="font-size:28px;font-weight:800;color:#fff;">Rehletna<span style="color:#f5a623;">.tn</span></div>
      <div style="font-size:40px;margin:16px 0 8px;">🎉</div>
      <h1 style="color:#fff;font-size:22px;font-weight:700;margin:0;">Booking Confirmed!</h1>
      <p style="color:rgba(255,255,255,0.8);margin:8px 0 0;font-size:14px;">Your PDF ticket is attached to this email.</p>
    </div>

    <div style="padding:32px;">
      <p style="font-size:16px;color:#0d1f1e;margin:0 0 24px;">
        Hi <strong>{$user->getName()}</strong>, your reservation for <strong>{$offer->getTitle()}</strong> is confirmed and paid!
      </p>

      <div style="background:#f0f4f3;border-radius:14px;padding:20px 24px;margin-bottom:24px;">
        <table style="width:100%;border-collapse:collapse;">
          <tr>
            <td style="padding:8px 0;color:#6b8282;font-size:14px;">Reservation ID</td>
            <td style="padding:8px 0;font-weight:700;color:#0d1f1e;text-align:right;">#{$reservation->getId()}</td>
          </tr>
          <tr style="border-top:1px solid #ddecea;">
            <td style="padding:8px 0;color:#6b8282;font-size:14px;">Offer</td>
            <td style="padding:8px 0;font-weight:600;color:#0d1f1e;text-align:right;">{$offer->getTitle()}</td>
          </tr>
          <tr style="border-top:1px solid #ddecea;">
            <td style="padding:8px 0;color:#6b8282;font-size:14px;">Location</td>
            <td style="padding:8px 0;font-weight:600;color:#0d1f1e;text-align:right;">{$offer->getLocation()}</td>
          </tr>
          <tr style="border-top:1px solid #ddecea;">
            <td style="padding:8px 0;color:#6b8282;font-size:14px;">Trip dates</td>
            <td style="padding:8px 0;font-weight:600;color:#0d1f1e;text-align:right;">{$offer->getStartDate()?->format('d/m/Y')} → {$offer->getEndDate()?->format('d/m/Y')}</td>
          </tr>
          <tr style="border-top:1px solid #ddecea;">
            <td style="padding:8px 0;color:#6b8282;font-size:14px;">Persons</td>
            <td style="padding:8px 0;font-weight:600;color:#0d1f1e;text-align:right;">{$reservation->getNumberOfPersons()}</td>
          </tr>
          {$specialRequest}
          <tr style="border-top:2px solid #1dbcaa;">
            <td style="padding:12px 0 4px;color:#0d1f1e;font-size:16px;font-weight:700;">Total Paid</td>
            <td style="padding:12px 0 4px;font-size:20px;font-weight:800;color:#1dbcaa;text-align:right;">{$reservation->getTotalAmount()} TND</td>
          </tr>
        </table>
      </div>

      <div style="background:#e6f8f6;border-radius:10px;padding:14px 18px;font-size:13px;color:#0a7a70;margin-bottom:24px;">
        📎 Your booking ticket (PDF) is attached to this email. Please present it at check-in.
      </div>

      <p style="font-size:13px;color:#6b8282;text-align:center;margin:0;">
        Questions? Contact us at <a href="mailto:{$this->fromEmail}" style="color:#1dbcaa;">{$this->fromEmail}</a>
      </p>
    </div>

    <div style="background:#f0f4f3;padding:20px 32px;text-align:center;">
      <p style="font-size:12px;color:#9bbfbc;margin:0;">© {$this->fromName} · Automated confirmation email.</p>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function buildEmailText(Reservation $reservation): string
    {
        $user  = $reservation->getUser();
        $offer = $reservation->getOffer();

        return implode("\n", [
            "Booking Confirmed — Rehletna.tn",
            "================================",
            "Hi {$user->getName()},",
            "",
            "Your reservation is confirmed and paid. PDF ticket attached.",
            "",
            "Reservation #: {$reservation->getId()}",
            "Offer:         {$offer->getTitle()}",
            "Location:      {$offer->getLocation()}",
            "Dates:         {$offer->getStartDate()?->format('d/m/Y')} → {$offer->getEndDate()?->format('d/m/Y')}",
            "Persons:       {$reservation->getNumberOfPersons()}",
            "Total Paid:    {$reservation->getTotalAmount()} TND",
            $reservation->getSpecialRequest() ? "Special:       {$reservation->getSpecialRequest()}" : "",
            "",
            "Questions? Contact {$this->fromEmail}",
        ]);
    }
}