#
# build bin/pharext
#

all: bin/pharext bin/pharext.update

bin/%: build/%.php src/* src/pharext/* src/pharext/*/* src/pharext/*/*/*
	@echo "Linting changed source files ... "
	@for file in $?; do php -l $$file | sed -ne '/^No syntax errors/!p' && exit $${PIPESTATUS[0]}; done
	@echo "Creating $@ ... "
	php -d phar.readonly=0 $<

test:
	@echo "Running tests ... "
	@php -dphar.readonly=0 `which phpunit5` tests
	
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

.PHONY: all clean test release
