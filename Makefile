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

.PHONY: all clean test
.SUFFIXES: .php