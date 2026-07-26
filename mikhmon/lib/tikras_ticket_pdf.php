<?php
/*
 *  Lightweight PDF ticket helper for TIKRAS IT.
 *  Generates a compact PDF close to the small voucher template.
 */
include_once(dirname(__FILE__) . '/tikras_core.php');

if (!function_exists('tikras_pdf_text')) {
  function tikras_pdf_text($value)
  {
    $value = trim((string) $value);
    $value = str_replace(array("\r", "\n", "\t"), " ", $value);
    if (function_exists('iconv')) {
      $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
      if ($converted !== false) {
        return $converted;
      }
    }
    if (function_exists('utf8_decode')) {
      return utf8_decode($value);
    }
    return $value;
  }
}

if (!function_exists('tikras_pdf_fit')) {
  function tikras_pdf_fit($value, $max)
  {
    $value = (string) $value;
    if (strlen($value) <= $max) {
      return $value;
    }
    return substr($value, 0, max(0, $max - 3)) . "...";
  }
}

if (!class_exists('TikrasTicketPdf')) {
  class TikrasTicketPdf
  {
    private $pages = array();
    private $content = "";
    private $width = 595.28;
    private $height = 841.89;
    private $logo = null;

    public function __construct()
    {
      $this->addPage();
    }

    public function addPage()
    {
      if ($this->content !== "") {
        $this->pages[] = $this->content;
      }
      $this->content = "0.35 w\n";
    }

    private function pt($mm)
    {
      return $mm * 72 / 25.4;
    }

    private function y($mm)
    {
      return $this->height - $this->pt($mm);
    }

    private function esc($text)
    {
      return str_replace(array("\\", "(", ")"), array("\\\\", "\\(", "\\)"), tikras_pdf_text($text));
    }

    private function approxTextWidth($text, $size)
    {
      return strlen(tikras_pdf_text($text)) * $size * 0.48;
    }

    public function text($x, $y, $text, $size, $bold, $align, $maxWidth)
    {
      $font = $bold ? "F2" : "F1";
      $pdfX = $this->pt($x);
      $pdfY = $this->y($y);
      if ($align == "center") {
        $pdfX += ($this->pt($maxWidth) - $this->approxTextWidth($text, $size)) / 2;
      } elseif ($align == "right") {
        $pdfX += $this->pt($maxWidth) - $this->approxTextWidth($text, $size);
      }
      $this->content .= "BT /" . $font . " " . sprintf('%.2F', $size) . " Tf " . sprintf('%.2F %.2F', $pdfX, $pdfY) . " Td (" . $this->esc($text) . ") Tj ET\n";
    }

    public function rect($x, $y, $w, $h)
    {
      $this->content .= sprintf('%.2F %.2F %.2F %.2F re S', $this->pt($x), $this->y($y + $h), $this->pt($w), $this->pt($h)) . "\n";
    }

    /* Rectangle rempli, utilise pour les modules du QR. */
    public function rectPlein($x, $y, $w, $h)
    {
      $this->content .= sprintf('%.3F %.3F %.3F %.3F re f', $this->pt($x), $this->y($y + $h), $this->pt($w), $this->pt($h)) . "\n";
    }

    public function line($x1, $y1, $x2, $y2)
    {
      $this->content .= sprintf('%.2F %.2F m %.2F %.2F l S', $this->pt($x1), $this->y($y1), $this->pt($x2), $this->y($y2)) . "\n";
    }

    /*
     * Enregistre le logo, converti en JPEG car ce format s'insere tel quel
     * dans un PDF. Retourne false si l'image est illisible: le ticket est
     * alors simplement imprime sans logo.
     */
    public function chargerLogo($chemin)
    {
      if ($this->logo !== null) {
        return true;
      }
      if (!is_file($chemin) || !function_exists('imagecreatefromstring')) {
        return false;
      }
      $source = @imagecreatefromstring((string) @file_get_contents($chemin));
      if ($source === false) {
        return false;
      }
      $largeur = imagesx($source);
      $hauteur = imagesy($source);
      if ($largeur < 1 || $hauteur < 1) {
        imagedestroy($source);
        return false;
      }
      // Reduction: inutile d'embarquer une grande image pour quelques mm.
      $cible = 120;
      $ratio = min(1, $cible / max($largeur, $hauteur));
      $nl = max(1, (int) round($largeur * $ratio));
      $nh = max(1, (int) round($hauteur * $ratio));
      $vignette = imagecreatetruecolor($nl, $nh);
      // Fond blanc: la transparence n'existe pas en JPEG.
      $blanc = imagecolorallocate($vignette, 255, 255, 255);
      imagefilledrectangle($vignette, 0, 0, $nl, $nh, $blanc);
      imagecopyresampled($vignette, $source, 0, 0, 0, 0, $nl, $nh, $largeur, $hauteur);
      imagedestroy($source);

      ob_start();
      imagejpeg($vignette, null, 85);
      $donnees = ob_get_clean();
      imagedestroy($vignette);
      if ($donnees === false || $donnees === '') {
        return false;
      }
      $this->logo = array('data' => $donnees, 'w' => $nl, 'h' => $nh);
      return true;
    }

    public function aUnLogo()
    {
      return $this->logo !== null;
    }

    public function logo($x, $y, $w, $h)
    {
      if ($this->logo === null) {
        return;
      }
      $this->content .= sprintf(
        "q %.2F 0 0 %.2F %.2F %.2F cm /L1 Do Q",
        $this->pt($w), $this->pt($h), $this->pt($x), $this->y($y + $h)
      ) . "\n";
    }

    public function save($file)
    {
      if ($this->content !== "") {
        $this->pages[] = $this->content;
        $this->content = "";
      }

      $objects = array();
      $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
      $kids = array();
      // Le logo occupe un objet supplementaire quand il est present.
      $pageObjectId = $this->logo === null ? 5 : 6;
      for ($i = 0; $i < count($this->pages); $i++) {
        $kids[] = ($pageObjectId + ($i * 2)) . " 0 R";
      }
      $objects[] = "<< /Type /Pages /Kids [" . implode(" ", $kids) . "] /Count " . count($this->pages) . " >>";
      $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
      $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

      $ressourceLogo = "";
      if ($this->logo !== null) {
        $objects[] = "<< /Type /XObject /Subtype /Image /Width " . $this->logo['w']
          . " /Height " . $this->logo['h'] . " /ColorSpace /DeviceRGB /BitsPerComponent 8"
          . " /Filter /DCTDecode /Length " . strlen($this->logo['data']) . " >>\nstream\n"
          . $this->logo['data'] . "\nendstream";
        $ressourceLogo = " /XObject << /L1 5 0 R >>";
      }

      for ($i = 0; $i < count($this->pages); $i++) {
        $contentId = $pageObjectId + ($i * 2) + 1;
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . sprintf('%.2F %.2F', $this->width, $this->height) . "] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>" . $ressourceLogo . " >> /Contents " . $contentId . " 0 R >>";
        $stream = $this->pages[$i];
        $objects[] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
      }

      $pdf = "%PDF-1.4\n";
      $offsets = array(0);
      for ($i = 0; $i < count($objects); $i++) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $objects[$i] . "\nendobj\n";
      }
      $xref = strlen($pdf);
      $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
      for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
      }
      $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

      return file_put_contents($file, $pdf) !== false;
    }
  }
}

if (!function_exists('tikras_ticket_pdf_qr')) {
  /*
   * Dessine le QR en petits rectangles vectoriels: aucune image a produire,
   * et le rendu reste net a l'impression quelle que soit la resolution.
   */
  function tikras_ticket_pdf_qr($pdf, $texte, $x, $y, $cote)
  {
    if (!function_exists('tikras_qr_matrice')) {
      include_once(dirname(__FILE__) . '/tikras_qr.php');
    }
    $matrice = tikras_qr_matrice($texte);
    if (!is_array($matrice) || count($matrice) < 1) {
      return false;
    }
    $modules = count($matrice);
    $pas = $cote / $modules;
    for ($i = 0; $i < $modules; $i++) {
      for ($j = 0; $j < $modules; $j++) {
        if ($matrice[$i][$j] === 1) {
          $pdf->rectPlein($x + ($j * $pas), $y + ($i * $pas), $pas, $pas);
        }
      }
    }
    return true;
  }
}

if (!function_exists('tikras_ticket_pdf_draw_ticket')) {
  function tikras_ticket_pdf_draw_ticket($pdf, $ticket, $meta, $num, $x, $y)
  {
    $w = 45;
    $h = 28;
    $username = isset($ticket['username']) ? $ticket['username'] : "";
    $password = isset($ticket['password']) ? $ticket['password'] : "";
    $mode = ($password == "" || $username == $password) ? "vc" : "up";
    $hotspot = tikras_pdf_fit(isset($meta['hotspotname']) ? $meta['hotspotname'] : "Hotspot", 24);

    /*
     * Le ticket reprend les informations du modele imprimable: identifiants,
     * adresse de connexion et conditions. Sans l'adresse, le client ne sait
     * pas ou se connecter une fois le ticket en main.
     */
    $adresse = trim((string) (isset($meta['dnsname']) ? $meta['dnsname'] : ""));
    $adresse = $adresse != "" ? tikras_pdf_fit($adresse, 34) : "";

    $conditions = array();
    if (isset($meta['validity']) && trim((string) $meta['validity']) != "") {
      $conditions[] = "Validite " . trim((string) $meta['validity']);
    }
    if (isset($meta['timelimit']) && trim((string) $meta['timelimit']) != "" && $meta['timelimit'] != "0") {
      $conditions[] = trim((string) $meta['timelimit']);
    }
    if (isset($meta['datalimit']) && trim((string) $meta['datalimit']) != "" && $meta['datalimit'] != "0") {
      $conditions[] = trim((string) $meta['datalimit']);
    }
    $prix = trim((string) (isset($meta['price']) ? $meta['price'] : ""));
    if ($prix != "" && $prix != "0") {
      // Selon l'appelant, le prix porte deja la devise: on ne la repete pas.
      $devise = trim((string) (isset($meta['currency']) ? $meta['currency'] : ""));
      if ($devise != "" && stripos($prix, $devise) === false) {
        $prix = trim($prix . " " . $devise);
      }
      $conditions[] = $prix;
    }
    $details = tikras_pdf_fit(implode("  -  ", $conditions), 42);

    /*
     * Le QR est optionnel: il occupe la droite du ticket et decale le reste.
     * Il porte l'adresse du portail et le code, afin qu'un client puisse se
     * connecter sans rien recopier.
     */
    $avecQr = !empty($meta['qrcode']);
    $qrCote = 15.5;
    $largeurTexte = $avecQr ? ($w - $qrCote - 6) : ($w - 8);
    $gaucheTexte = $x + 4;

    $pdf->rect($x, $y, $w, $h);

    /*
     * Logo optionnel: place en tete, il decale le nom du hotspot. Absent, la
     * mise en page reste exactement celle d'origine.
     */
    $avecLogo = !empty($meta['logo']) && $pdf->aUnLogo();
    if ($avecLogo) {
      $pdf->logo($x + 1.6, $y + 1, 4.6, 4.6);
      $pdf->text($x + 7, $y + 4.2, tikras_pdf_fit($hotspot, 20), 7.5, true, "left", 26);
    } else {
      $pdf->text($x + 2, $y + 4.2, $hotspot, 8, true, "left", 30);
    }
    $pdf->text($x + 35, $y + 4.2, "[" . $num . "]", 7, true, "right", 8);
    $pdf->line($x, $y + 5.8, $x + $w, $y + 5.8);

    if ($avecQr) {
      /*
       * Le QR est propre a chaque ticket: l'eventuel prefixe commun (adresse
       * du portail) est suivi du code du ticket, sans quoi tous les QR d'une
       * page renverraient la meme information.
       */
      $prefixeQr = trim((string) (isset($meta['qrtexte']) ? $meta['qrtexte'] : ""));
      if ($prefixeQr == "" && $adresse != "") {
        $prefixeQr = "http://" . $adresse;
      }
      $contenuQr = trim($prefixeQr . " " . $username);
      if ($mode != "vc" && $password != "") {
        $contenuQr .= " / " . $password;
      }
      if (!tikras_ticket_pdf_qr($pdf, $contenuQr, $x + $w - $qrCote - 2.5, $y + 8.5, $qrCote)) {
        // Contenu trop long pour un QR: on rend la largeur au texte.
        $avecQr = false;
        $largeurTexte = $w - 8;
      }
    }

    if ($mode == "vc") {
      $pdf->text($gaucheTexte, $y + 9.6, "Code d'acces", 6.5, false, "center", $largeurTexte);
      $pdf->rect($gaucheTexte, $y + 10.6, $largeurTexte, 6);
      $pdf->text($gaucheTexte, $y + 14.8, tikras_pdf_fit($username, $avecQr ? 14 : 22), $avecQr ? 8 : 9, true, "center", $largeurTexte);
    } else {
      $demi = $largeurTexte / 2;
      $pdf->text($gaucheTexte, $y + 9.6, "Utilisateur", 5.5, false, "center", $demi);
      $pdf->text($gaucheTexte + $demi, $y + 9.6, "Mot de passe", 5.5, false, "center", $demi);
      $pdf->rect($gaucheTexte, $y + 10.6, $demi, 6);
      $pdf->rect($gaucheTexte + $demi, $y + 10.6, $demi, 6);
      $pdf->text($gaucheTexte, $y + 14.8, tikras_pdf_fit($username, 12), 6.8, true, "center", $demi);
      $pdf->text($gaucheTexte + $demi, $y + 14.8, tikras_pdf_fit($password, 12), 6.8, true, "center", $demi);
    }

    if ($adresse != "") {
      $pdf->text($gaucheTexte, $y + 20.2, "Connexion : " . tikras_pdf_fit($adresse, $avecQr ? 22 : 34), 5.8, false, "center", $largeurTexte);
    }
    if ($details != "") {
      $pdf->rect($gaucheTexte, $y + 21.6, $largeurTexte, 5);
      $pdf->text($gaucheTexte, $y + 25, tikras_pdf_fit($details, $avecQr ? 28 : 42), 5.8, true, "center", $largeurTexte);
    }
  }
}

if (!function_exists('tikras_ticket_pdf_generate')) {
  function tikras_ticket_pdf_generate($file, $tickets, $meta)
  {
    if (!is_array($tickets) || count($tickets) < 1) {
      return false;
    }
    $dir = dirname($file);
    if (!is_dir($dir)) {
      @mkdir($dir, 0777, true);
    }

    $pdf = new TikrasTicketPdf();
    // Le logo n'est charge qu'une fois, et seulement s'il a ete demande.
    if (!empty($meta['logo']) && is_string($meta['logo'])) {
      $pdf->chargerLogo($meta['logo']);
    }
    $marginX = 7;
    $marginY = 9;
    $ticketW = 45;
    $ticketH = 28;
    $gapX = 3;
    $gapY = 3;
    $cols = 4;
    $rows = 9;

    for ($i = 0; $i < count($tickets); $i++) {
      if ($i > 0 && $i % ($cols * $rows) == 0) {
        $pdf->addPage();
      }
      $slot = $i % ($cols * $rows);
      $col = $slot % $cols;
      $row = floor($slot / $cols);
      $x = $marginX + ($col * ($ticketW + $gapX));
      $y = $marginY + ($row * ($ticketH + $gapY));
      tikras_ticket_pdf_draw_ticket($pdf, $tickets[$i], $meta, $i + 1, $x, $y);
    }

    return $pdf->save($file);
  }
}

if (!function_exists('tikras_ticket_public_url')) {
  function tikras_ticket_public_url($relativePath)
  {
    $scheme = (tikras_server('HTTPS') != "" && tikras_server('HTTPS') != 'off') ? 'https' : 'http';
    $host = tikras_server('HTTP_HOST', 'localhost');
    $script = str_replace('\\', '/', tikras_server('SCRIPT_NAME', '/index.php'));
    $base = rtrim(dirname($script), '/');
    if ($base == "/" || $base == ".") {
      $base = "";
    }
    return $scheme . "://" . $host . $base . "/" . ltrim($relativePath, "/");
  }
}

if (!function_exists('tikras_ticket_send_pdf_mail')) {
  function tikras_ticket_send_pdf_mail($to, $subject, $body, $pdfPath, $pdfName, $from, $fromName = "")
  {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !is_file($pdfPath)) {
      return false;
    }
    $separator = md5((string) time());
    $attachment = chunk_split(base64_encode(file_get_contents($pdfPath)));
    $from = filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : "tickets@tikras-it.local";
    $fromName = trim(str_replace(array("\r", "\n", "\""), " ", (string) $fromName));
    $fromHeader = $fromName != "" ? "\"" . $fromName . "\" <" . $from . ">" : $from;

    $headers = "From: " . $fromHeader . "\r\n";
    $headers .= "Reply-To: " . $from . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"" . $separator . "\"\r\n";

    $message = "--" . $separator . "\r\n";
    $message .= "Content-Type: text/plain; charset=\"utf-8\"\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $body . "\r\n\r\n";
    $message .= "--" . $separator . "\r\n";
    $message .= "Content-Type: application/pdf; name=\"" . $pdfName . "\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"" . $pdfName . "\"\r\n\r\n";
    $message .= $attachment . "\r\n";
    $message .= "--" . $separator . "--";

    return @mail($to, $subject, $message, $headers);
  }
}
?>
