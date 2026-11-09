#!/usr/bin/env bash
# Runs the dumper, auto-stubbing any missing WordPress function/class/constant it hits.
PHP="${PHP:-php}"
DIR="$(cd "$(dirname "$0")" && pwd)"
for i in $(seq 1 600); do
  OUT=$("$PHP" "$DIR/bootstrap.php" "$@" 2>&1)
  if echo "$OUT" | grep -q "Fatal error\|Uncaught"; then
    # "Call to undefined function Some\Ns\wp_foo()" -> wp_foo
    fn=$(echo "$OUT" | grep -o 'Call to undefined function [A-Za-z0-9_\\]*' | head -1 | sed 's/.*function //' | awk -F'\\' '{print $NF}')
    if [ -n "$fn" ]; then
      echo "function $fn(...\$a) { return \$a[0] ?? null; }" >> "$DIR/shim.php"; echo "stub fn $fn"; continue
    fi
    cl=$(echo "$OUT" | grep -o 'Class "[A-Za-z0-9_]*" not found' | head -1 | sed 's/Class "//; s/" not found//')
    if [ -n "$cl" ]; then
      echo "class $cl { public function __construct(...\$a) {} public function __call(\$n, \$a) { return null; } public static function __callStatic(\$n, \$a) { return null; } }" >> "$DIR/shim.php"; echo "stub class $cl"; continue
    fi
    k=$(echo "$OUT" | grep -o 'Undefined constant "[A-Za-z0-9_]*"' | head -1 | sed 's/Undefined constant "//; s/"//')
    if [ -n "$k" ]; then
      echo "if (!defined('$k')) define('$k', '');" >> "$DIR/shim.php"; echo "stub const $k"; continue
    fi
    echo "$OUT" | head -20; exit 1
  fi
  echo "$OUT" | tail -20; exit 0
done
echo "too many iterations"; exit 1
