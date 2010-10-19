check :
	php -l action.php

deploy : check
	scp action.php \
	    root@duck.fensystems.co.uk:/usr/share/dokuwiki/lib/plugins/errno/

.PHONY : check deploy
