#
# build bin/pharext
#

all: bin/pharext

bin/pharext: src/* src/pharext/*
	@for file in $?; do php -l $$file | sed -ne '/^No syntax errors/!p' && exit $${PIPESTATUS[0]}; done
	phpunit tests
	php -d phar.readonly=0 build/create-phar.php
	chmod +x $@

test:
	phpunit tests
	
clean:
	rm bin/pharext*

release:
	echo
	echo "Previous: $$(git tag --list | tail -n1)"; \
	read -p "Version:  v" VERSION; \
	sed -i '' -e "s/@PHAREXT_VERSION@/v$$VERSION/" src/pharext/Version.php; \
	$(MAKE); \
	git ci -am "release v$$VERSION"; \
	git tag v$$VERSION; \
	sed -i '' -e "s/v$$VERSION/@PHAREXT_VERSION@/" src/pharext/Version.php; \
	git ci -am "back to dev"


.PHONY: all clean test release
