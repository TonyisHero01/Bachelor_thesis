#!/bin/bash

create_venv_and_install() {
  local SCRIPTS_DIR="$1"
  local VENV_PATH="$SCRIPTS_DIR/venv"
  local REQUIREMENTS_FILE="$SCRIPTS_DIR/requirements.txt"

  if [ ! -d "$SCRIPTS_DIR" ]; then
    echo "⏭️  Python scripts directory not found: $SCRIPTS_DIR, skipping."
    return 0
  fi

  if [ -f "$VENV_PATH/bin/activate" ]; then
    echo "✅ Python venv already exists at $VENV_PATH, skipping installation."
    return 0
  fi

  echo "🔧 Creating new Python virtual environment in $SCRIPTS_DIR ..."
  python3 -m venv "$VENV_PATH"

  if [ ! -f "$REQUIREMENTS_FILE" ]; then
    echo "⚠️  Requirements file not found: $REQUIREMENTS_FILE"
    return 0
  fi

  echo "📦 Installing Python dependencies from $REQUIREMENTS_FILE ..."
  source "$VENV_PATH/bin/activate"

  pip install --upgrade pip
  pip install -r "$REQUIREMENTS_FILE"

  deactivate

  echo "✅ Python virtual environment ready at $VENV_PATH"
}