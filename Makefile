#
# build bin/pharext
#

all: bin/pharext

bin/pharext: src/* src/pharext/*
	php -d phar.readonly=0 build/create-phar.php
	chmod +x $@

clean:
	rm bin/pharext*

.PHONY: all clean
	
