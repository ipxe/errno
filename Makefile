SERVER = root@ipxe.org
DOKUWIKI = /usr/share/dokuwiki

check :
	php -l action.php

deploy : check
	ssh $(SERVER) mkdir -p $(DOKUWIKI)/lib/plugins/errno 
	scp action.php $(SERVER):$(DOKUWIKI)/lib/plugins/errno/

.PHONY : check deploy
