<?php
$password = 'baron123';
$hash = '$2b$12$mdkETI8K024eZxZivQrPq.FS4wRPkBEr005w1Ir5ndNymYbdcm6';

if (password_verify($password, $hash)) {
    echo "Le mot de passe correspond au hash\n";
} else {
    echo "Le mot de passe ne correspond pas au hash\n";
}
?> 