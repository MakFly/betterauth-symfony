#!/bin/bash
# Sync core-embedded between the two packages
# Usage: ./sync-core.sh <to|from> <path-to-other-package>
#
# Examples:
#   ./sync-core.sh to ../betterauth-laravel     # Push local core to laravel
#   ./sync-core.sh from ../betterauth-laravel    # Pull core from laravel

set -euo pipefail

DIRECTION="${1:-}"
OTHER="${2:-}"

if [ -z "$DIRECTION" ] || [ -z "$OTHER" ]; then
    echo "Usage: $0 <to|from> <path-to-other-package>"
    echo "  to   — copy local core-embedded/ to other package"
    echo "  from — copy other package's core-embedded/ to local"
    exit 1
fi

if [ ! -d "core-embedded" ]; then
    echo "Error: core-embedded/ not found in current directory"
    exit 1
fi

if [ "$DIRECTION" = "to" ]; then
    echo "Syncing core-embedded → $OTHER/core-embedded/"
    rm -rf "$OTHER/core-embedded"
    cp -r core-embedded "$OTHER/core-embedded"
    echo "Done. $(find "$OTHER/core-embedded" -type f | wc -l) files synced."
elif [ "$DIRECTION" = "from" ]; then
    if [ ! -d "$OTHER/core-embedded" ]; then
        echo "Error: $OTHER/core-embedded/ not found"
        exit 1
    fi
    echo "Syncing $OTHER/core-embedded/ → core-embedded/"
    rm -rf core-embedded
    cp -r "$OTHER/core-embedded" core-embedded
    echo "Done. $(find core-embedded -type f | wc -l) files synced."
else
    echo "Error: direction must be 'to' or 'from'"
    exit 1
fi
