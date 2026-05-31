<?php
$c=ftp_connect('204.93.224.158');
ftp_login($c,'sistemasaas@romaagencia.lat','TheRomaAgency2026@2222');
ftp_pasv($c,true);
print_r(ftp_nlist($c, '.'));
?>
