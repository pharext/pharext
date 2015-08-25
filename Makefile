#
# build bin/pharext
#

all: bin/pharext

bin/pharext: src/* src/pharext/* src/pharext/*/* src/pharext/*/*/*
	@echo "Linting changed source files ... "
	@for file in $?; do php -l $$file | sed -ne '/^No syntax errors/!p' && exit $${PIPESTATUS[0]}; done
	@echo "Creating bin/pharext ... "
	php -d phar.readonly=0 build/create-phar.php

test:
	@echo "Running tests ... "
	@php -dphar.readonly=0 `which phpunit` tests
	
clean:
	rm bin/pharext*

release:
	@echo "Previous Version: $$(git tag --list | tail -n1)"; \
	read -p "Release Version:  v" VERSION; \
	echo "Preparing release ... "; \
	sed -e "s/@dev-master/$$VERSION/" build/Metadata.php.in > src/pharext/Metadata.php && \
	$(MAKE) -B SIGN=1 && \
	git ci -am "release v$$VERSION" && \
	git tag v$$VERSION && \
	cp build/Metadata.php.in src/pharext/Metadata.php && \
	git ci -am "back to dev"

archive-test: bin/pharext
	./bin/pharext -vpgs ../apfd.git
	-../php-5.5/sapi/cli/php ./apfd-1.0.1.ext.phar
	-./apfd-1.0.1.ext.phar

.PHONY: all clean test release
