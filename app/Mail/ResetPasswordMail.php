<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * URL completa para restablecer la contraseña.
     */
    public string $resetUrl;

    /**
     * Creamos la instancia inyectando el usuario y el token generado por el framework.
     */
    public function __construct(
        public User $user,
        public string $token
    ) {
        // Construimos la URL estándar que espera Laravel Breeze / Fortify
        $this->resetUrl = route('password.reset', [
            'token' => $this->token,
            'email' => $this->user->email,
        ]);
    }

    /**
     * Define el asunto y remitentes del correo.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Restablecer Contraseña - Consultor UNESYS',
        );
    }

    /**
     * Define la vista y las variables que se le pasarán.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password',
            with: [
                'nombre' => $this->user->name,
                'url' => $this->resetUrl,
            ],
        );
    }
}