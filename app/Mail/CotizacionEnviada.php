<?php

namespace App\Mail;

// UBICACIÓN: app/Mail/CotizacionEnviada.php

use App\Models\Cotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class CotizacionEnviada extends Mailable
{
    use Queueable, SerializesModels;

    public Cotizacion $cotizacion;

    /**
     * Create a new message instance.
     */
    public function __construct(Cotizacion $cotizacion)
    {
        $this->cotizacion = $cotizacion->load(['cliente', 'empresa']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cotización ' . $this->cotizacion->folio . ' - ' . $this->cotizacion->empresa->razon_social,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.cotizacion-enviada',
            with: [
                'cotizacion' => $this->cotizacion,
                'empresa' => $this->cotizacion->empresa,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->cotizacion->pdf_path && file_exists(storage_path('app/' . $this->cotizacion->pdf_path))) {
            $attachments[] = Attachment::fromPath(storage_path('app/' . $this->cotizacion->pdf_path))
                ->as('Cotizacion_' . $this->cotizacion->folio . '.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}