#!/bin/bash

echo "Fixing critical Yoda conditions..."

# Fix specific problematic patterns
find src -name "*.php" -type f -exec sed -i '' \
  -e 's/if ( $\([a-zA-Z_][a-zA-Z0-9_]*\) === \(['"'"'"]\)/if ( \2 === $\1/g' \
  -e 's/if ( $\([a-zA-Z_][a-zA-Z0-9_]*\) == \(['"'"'"]\)/if ( \2 === $\1/g' \
  {} \;

# Fix main plugin file
sed -i '' \
  -e 's/if ( $\([a-zA-Z_][a-zA-Z0-9_]*\) === \(['"'"'"]\)/if ( \2 === $\1/g' \
  -e 's/if ( $\([a-zA-Z_][a-zA-Z0-9_]*\) == \(['"'"'"]\)/if ( \2 === $\1/g' \
  business-directory.php

echo "Done!"
