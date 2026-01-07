#!/bin/bash

echo "🚀 Frontweb container startup initiated."

source /common_python_venv.sh

echo "✅ Frontweb is ready."

exec apache2-foreground