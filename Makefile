check :
	php -l action.php

deploy : check
	scp action.php \
	    root@ipxe.org:/usr/share/dokuwiki/lib/plugins/errno/

.PHONY : check deploy
