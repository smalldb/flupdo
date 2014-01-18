
all: doc

tests:
	echo ; pear run-tests ./test ; echo

doc:
	make -C doc/


.PHONY: all tests doc

