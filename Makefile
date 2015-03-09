#
# build bin/pharext
#

all: bin/pharext

bin/pharext: src/* src/pharext/* src/pharext/*/*
	@echo "Linting changed source files ... "
	@for file in $?; do php -l $$file | sed -ne '/^No syntax errors/!p' && exit $${PIPESTATUS[0]}; done
	@echo "Creating bin/pharext ... "
	php -d phar.readonly=0 build/create-phar.php
	chmod +x $@

test:
	@echo "Running tests ... "
	@phpunit tests
	
clean:
	rm bin/pharext*

release:
	@echo "Previous Version: $$(git tag --list | tail -n1)"; \
	read -p "Release Version:  v" VERSION; \
	echo "Preparing release ... "; \
	sed -e "s/@PHAREXT_VERSION@/$$VERSION/" build/Version.php.in > src/pharext/Version.php && \
	$(MAKE) -B SIGN=1 && \
	git ci -am "release v$$VERSION" && \
	git tag v$$VERSION && \
	cp build/Version.php.in src/pharext/Version.php && \
	git ci -am "back to dev"

.PHONY: all clean test release
