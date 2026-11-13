<?php
declare(strict_types=1);
namespace Agora\Services;
/** Documento PDF simple y determinista, sin HTML remoto ni ejecución de plantillas. */
final class Pdf {
 public static function make(array $lines):string{
  $wrapped=[];foreach($lines as $line){$s=iconv('UTF-8','Windows-1252//TRANSLIT',(string)$line)?:'';foreach(explode("\n",wordwrap($s,95,"\n",true)) as $l)$wrapped[]=$l;}
  $pages=array_chunk($wrapped,48);$objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];$kids=[];$id=4;
  foreach($pages as $page){$p=$id++;$stream=$id++;$kids[]="$p 0 R";$text="BT /F1 11 Tf 48 790 Td 15 TL\n";foreach($page as $line)$text.='('.str_replace(['\\','(',')',"\r"],['\\\\','\\(','\\)',''],$line).") Tj T*\n";$text.='ET';$objects[$p]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $stream 0 R >>";$objects[$stream]='<< /Length '.strlen($text).">>\nstream\n$text\nendstream";}
  $objects[2]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $n=>$o){$offsets[$n]=strlen($pdf);$pdf.="$n 0 obj\n$o\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 $id\n0000000000 65535 f \n";for($i=1;$i<$id;$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);$pdf.="trailer << /Size $id /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";return $pdf;
 }
}
