
all: doc test

test:
	echo ; pear run-tests ./test ; echo

doc:
	make -C doc/


.PHONY: all test doc

