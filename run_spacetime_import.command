#!/bin/bash
# Double-click this file in Finder to run the Spacetime DevBoard import.
cd "$(dirname "$0")"
echo "=== Spacetime DevBoard Import ==="
echo "Running from: $(pwd)"
echo ""
python3 push/gimportspacetime.py
echo ""
echo "Done. Press any key to close..."
read -n 1
