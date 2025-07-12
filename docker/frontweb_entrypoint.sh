#!/bin/bash

echo "🚀 Frontweb container startup initiated."

source /common_python_venv.sh
create_venv_and_install "/var/www/html/python_scripts"

exec apache2-foreground