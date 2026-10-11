<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $decision, // approved|rejected
        public string $name = '',
        public string $store = '',
        public string $reason = '',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->decision === 'approved'
                ? 'Your INVOIZ Seller Application Was Approved'
                : 'Update on Your INVOIZ Seller Application'
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->renderHtml());
    }

    protected function renderHtml(): string
    {
        $name = e($this->name ?: 'there');
        $store = e($this->store ?: 'your store');
        $ok = $this->decision === 'approved';
        $accent = $ok ? '#1D6B43' : '#B91C1C';
        $bg = $ok ? '#E8F5EE' : '#FDECEC';
        $title = $ok ? 'Congratulations!' : 'Application update';
        $body = $ok
            ? "Your seller application for <b>{$store}</b> has been <b>approved</b>. Log in and open your Seller Dashboard to add products and start selling."
            : "After review, your seller application for <b>{$store}</b> was <b>not approved</b> at this time."
                . ($this->reason !== '' ? '<br><br>Reason: ' . e($this->reason) : '')
                . '<br><br>You may update your details and apply again from your profile.';
        return <<<HTML
<div style="font-family:'Segoe UI',Arial,sans-serif;background:#F8FAF9;padding:32px 16px">
  <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:24px;padding:36px;box-shadow:0 10px 30px rgba(14,74,87,.08)">
    <div style="text-align:center;font-size:22px;font-weight:800;color:#0E4A57">INVOIZ</div>
    <div style="text-align:center;background:{$bg};color:{$accent};font-weight:800;border-radius:12px;padding:10px;margin:18px 0">{$title}</div>
    <p style="color:#374151;font-size:14px">Hi {$name},</p>
    <p style="color:#6B7280;font-size:13px;line-height:1.7">{$body}</p>
    <p style="color:#9CA3AF;font-size:11px;margin-top:18px;text-align:center">Life's short. Shop fast.</p>
  </div>
</div>
HTML;
    }
}
