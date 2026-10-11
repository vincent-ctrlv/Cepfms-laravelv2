<?php

namespace App\Services;

use Illuminate\Http\Response;
use TCPDF;

/**
 * Password-protected PDFs of staff pages (Engagement Analytics, Dashboard), in
 * two kinds:
 *   - the page as it looks: the browser renders each section to a JPEG
 *     (html2canvas, see CivicConnect.protectedPdf in public/frontend/js/app.js)
 *     and posts them; send() stacks them onto landscape A4 pages;
 *   - a table report built here (sendTables()).
 * Either way the PDF is encrypted (AES-256) with a fresh random password that
 * is emailed to the person exporting, so a forwarded copy stays unreadable.
 */
class ProtectedPdf
{
    public const MAX_SECTIONS = 40;
    public const MAX_SECTION_BYTES = 6 * 1024 * 1024;

    /** The posted section images, checked to really be JPEGs; stops with 422 otherwise. */
    public static function sections(array $input): array
    {
        $images = is_array($input['images'] ?? null) ? $input['images'] : [];
        if (!$images || count($images) > self::MAX_SECTIONS) {
            json_fail('Nothing to export. Please reload the page and try again.', 422);
        }
        $sections = [];
        foreach ($images as $dataUrl) {
            $prefix = 'data:image/jpeg;base64,';
            if (!is_string($dataUrl) || !str_starts_with($dataUrl, $prefix)) {
                json_fail('Invalid page image.', 422);
            }
            $bytes = base64_decode(substr($dataUrl, strlen($prefix)), true);
            $info = $bytes !== false && strlen($bytes) <= self::MAX_SECTION_BYTES ? @getimagesizefromstring($bytes) : false;
            if (!$info || $info[2] !== IMAGETYPE_JPEG || $info[0] < 1 || $info[1] < 1) {
                json_fail('Invalid page image.', 422);
            }
            $sections[] = ['bytes' => $bytes, 'w' => $info[0], 'h' => $info[1]];
        }
        return $sections;
    }

    /**
     * A fresh file password, emailed to the person exporting before anything is
     * built: no email means a file nobody could open, so stop with 500 instead.
     */
    private static function emailedPassword(array $staff, string $fileName): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < 10; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        if (!EmailService::sendFilePassword($staff['email'], $fileName, $password)) {
            json_fail('Could not email the file password. Please try again shortly.', 500);
        }
        return $password;
    }

    /** An empty encrypted A4 PDF ('L'andscape or 'P'ortrait) with the title line on page 1. */
    private static function newPdf(array $staff, string $title, string $password, string $orientation, float $margin, array $subtitleLines = []): TCPDF
    {
        $pdf = new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator(config('app.name', 'CEPFMS'));
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins($margin, $margin, $margin);
        $pdf->SetAutoPageBreak(false);
        // Mode 3 = AES-256. The owner password is random and never shown.
        $pdf->SetProtection([], $password, bin2hex(random_bytes(16)), 3);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor(15, 30, 61);
        $pdf->Cell(0, 7, $title, 0, 1);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetTextColor(100, 116, 139);
        foreach ($subtitleLines as $line) {
            $pdf->Cell(0, 4.5, $line, 0, 1);
        }
        $pdf->Cell(0, 5, 'Exported by ' . $staff['full_name'] . ' (' . $staff['email'] . ') on ' . date('M j, Y g:i A'), 0, 1);
        return $pdf;
    }

    /** Calls $log (the file exists now), then returns it: inline to print from, or as a download. */
    private static function output(TCPDF $pdf, array $staff, string $fileName, string $password, bool $forPrint, callable $log): Response
    {
        $pdfBytes = $pdf->Output($fileName, 'S');

        $log();

        $headers = [
            'Content-Type'            => 'application/pdf',
            'Content-Disposition'     => ($forPrint ? 'inline' : 'attachment') . "; filename=\"$fileName\"",
            'Content-Length'          => (string) strlen($pdfBytes),
            'Cache-Control'           => 'no-store',
            'X-File-Password-Sent-To' => $staff['email'],
        ];
        if (EmailService::isDevMode()) {
            $headers['X-Dev-File-Password'] = $password; // local development only, like the login OTP
        }
        return response($pdfBytes, 200, $headers);
    }

    /** The page as it looks: the section images stacked on landscape pages. */
    public static function send(array $staff, string $title, string $fileName, array $sections, bool $forPrint, callable $log): Response
    {
        $password = self::emailedPassword($staff, $fileName);
        $margin = 10;
        $pdf = self::newPdf($staff, $title, $password, 'L', $margin);

        $pageW = $pdf->getPageWidth() - 2 * $margin;
        $pageBottom = $pdf->getPageHeight() - $margin;
        $y = $pdf->GetY() + 3;
        $gap = 3;
        foreach ($sections as $s) {
            // Full content width; a section taller than a whole page is shrunk to fit one.
            $w = $pageW;
            $h = $w * $s['h'] / $s['w'];
            $maxH = $pageBottom - $margin;
            if ($h > $maxH) {
                $h = $maxH;
                $w = $h * $s['w'] / $s['h'];
            }
            if ($y + $h > $pageBottom) {
                $pdf->AddPage();
                $y = $margin;
            }
            $pdf->Image('@' . $s['bytes'], $margin + ($pageW - $w) / 2, $y, $w, $h, 'JPG');
            $y += $h + $gap;
        }

        return self::output($pdf, $staff, $fileName, $password, $forPrint, $log);
    }

    /**
     * A report of headed tables on portrait pages. $tables = [[heading, [column, ...],
     * [[cell, ...], ...]], ...]. A table that fits on one page never splits; a longer
     * one continues on the next page with its header row repeated.
     */
    public static function sendTables(array $staff, string $title, string $fileName, array $subtitleLines, array $tables, bool $forPrint, callable $log): Response
    {
        $password = self::emailedPassword($staff, $fileName);
        $margin = 15;
        $pdf = self::newPdf($staff, $title, $password, 'P', $margin, $subtitleLines);
        $pdf->SetAutoPageBreak(true, $margin);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Ln(4);
        $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $usable = $pdf->getPageHeight() - 2 * $margin;

        foreach ($tables as [$heading, $columns, $rows]) {
            // Heading + table stay together: start a new page if this one can't hold them.
            $estimate = 12 + 6.2 * (count($rows) + 1);
            if ($estimate < $usable && $pdf->GetY() + $estimate > $pdf->getPageHeight() - $margin) {
                $pdf->AddPage();
            }
            $html = '<h3 style="color:#0f1e3d;font-size:11pt;">' . $h($heading) . '</h3>'
                . '<table cellpadding="4" border="0.5" style="border-color:#cbd5e1;font-size:9pt;">'
                . '<thead><tr style="background-color:#f1f5f9;font-weight:bold;">';
            foreach ($columns as $c) $html .= '<td>' . $h($c) . '</td>';
            $html .= '</tr></thead>';
            foreach ($rows as $r) {
                $html .= '<tr nobr="true">';
                foreach ($r as $c) $html .= '<td>' . $h($c) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Ln(3);
        }

        return self::output($pdf, $staff, $fileName, $password, $forPrint, $log);
    }
}
