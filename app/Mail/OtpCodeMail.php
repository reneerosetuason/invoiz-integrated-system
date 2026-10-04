<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $name = '')
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your INVOIZ verification code: ' . $this->code);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->renderHtml());
    }

    protected function renderHtml(): string
    {
        $code = e($this->code);
        $name = e($this->name ?: 'there');
        return <<<HTML
<div style="font-family:'Segoe UI',Arial,sans-serif;background:#F8FAF9;padding:32px 16px">
  <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:24px;padding:36px;box-shadow:0 10px 30px rgba(14,74,87,.08)">
    <div style="text-align:center;font-size:22px;font-weight:800;color:#0E4A57;letter-spacing:-.5px">INVOIZ</div>
    <p style="color:#374151;font-size:14px;margin:20px 0 6px">Hi {$name},</p>
    <p style="color:#6B7280;font-size:13px;margin:0 0 18px">Use this code to verify your email. It expires in <b>10 minutes</b>.</p>
    <div style="text-align:center;background:#EAF4F3;border-radius:16px;padding:20px;letter-spacing:10px;font-size:32px;font-weight:800;color:#0E4A57">{$code}</div>
    <p style="color:#9CA3AF;font-size:11px;margin:18px 0 0;text-align:center">Didn't request this? Ignore this email.<br>Life's short. Shop fast.</p>
  </div>
</div>
HTML;
    }
}
