chown -R user:user .
chgrp www-data .
chmod 750 .
setfacl -Rdm u:www-data:rwx,u:user:rwx storage/
setfacl -Rm u:www-data:rwX,u:user:rwX storage/
setfacl -Rdm u:www-data:rwx,u:phit:rwx bootstrap/cache/
setfacl -Rm u:www-data:rwX,u:phit:rwX bootstrap/cache/