# Instalación en IONOS

El manual completo con capturas está en docs/manual.md. Dentro del panel, "Ayuda y tutorial" ofrece recorridos guiados.

1. En el panel de IONOS crea una base de datos MySQL (Hosting > Bases de datos) y anota: nombre de host, nombre de la base, usuario y contraseña.
2. Comprueba que el dominio usa PHP 8.0 o superior (Hosting > PHP).
3. Sube por SFTP todo el contenido de esta carpeta al directorio del dominio (o a una subcarpeta, por ejemplo /eventos). Incluye los ficheros ocultos .htaccess.
4. Abre la dirección en el navegador. Aparece el instalador: rellena los datos de la base de datos, el nombre de la marca y tu usuario de administración.
5. Entra en Ajustes > Cobros y configura IBAN, Bizum o Stripe. En Ajustes > Negocio indica un correo remitente de tu propio dominio.
6. Activa el certificado SSL del dominio (la cámara del lector QR solo funciona con https).

Las carpetas config, storage y uploads necesitan permiso de escritura (755 o 775).

Para reinstalar desde cero: borra config/config.php y vacía la base de datos.

Stripe: en el panel de Stripe crea un webhook hacia https://tudominio/stripe/webhook con el evento checkout.session.completed y pega su secreto en Ajustes > Cobros.

Librerías incluidas: qrcode-generator y jsQR (licencias MIT y Apache 2.0).
