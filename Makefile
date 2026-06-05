ifeq ($(OS),Windows_NT)
	EXT := target/release/php_jsonfast.dll
	EXT_DEBUG := target/debug/php_jsonfast.dll
else
	UNAME_S := $(shell uname -s)
	ifeq ($(UNAME_S),Darwin)
		EXT := target/release/libphp_jsonfast.dylib
		EXT_DEBUG := target/debug/libphp_jsonfast.dylib
	else
		EXT := target/release/libphp_jsonfast.so
		EXT_DEBUG := target/debug/libphp_jsonfast.so
	endif
endif

PHP ?= php
PHP_FLAGS = -d zend.assertions=1 -d assert.exception=1 -d extension=$(EXT)
PHP_RUN = $(PHP) $(PHP_FLAGS)

.PHONY: build release stubs test test-all test-basic test-output test-path test-analyse-repair test-schema test-diff coverage clean-coverage benchmark examples

build:
	cargo build --release

LIBPHP_DIR ?= /usr/lib

release: build stubs test-all

LIBPHP_SO ?= $(LIBPHP_DIR)/libphp.so

# ext_php_rs_describe_module is only exported in debug builds (cfg(debug_assertions))
stubs:
	cargo build
	LD_LIBRARY_PATH=$(LIBPHP_DIR):$$LD_LIBRARY_PATH \
	LD_PRELOAD=$(LIBPHP_SO) \
	cargo php stubs $(EXT_DEBUG) --stdout > php_jsonfast.stub.php

test: test-all

test-all: build
	$(PHP_RUN) tests/run_all.php

test-basic: build
	$(PHP_RUN) tests/basic.php

test-output: build
	$(PHP_RUN) tests/output.php

test-path: build
	$(PHP_RUN) tests/path.php

test-analyse-repair: build
	$(PHP_RUN) tests/analyse_repair.php

test-schema: build
	$(PHP_RUN) tests/schema.php

test-diff: build
	$(PHP_RUN) tests/diff.php

coverage: build
	@mkdir -p coverage
	$(PHP_RUN) tests/coverage.php

clean-coverage:
	rm -rf coverage

BENCH_ITERATIONS ?= 1000
BENCH_ITEMS ?= 500
BENCH_CAPACITY ?= 200000

benchmark: build
	$(PHP_RUN) benchmarks/benchmark.php $(BENCH_ITERATIONS) $(BENCH_ITEMS) $(BENCH_CAPACITY)

examples: build
	$(PHP_RUN) examples/run_all.php
