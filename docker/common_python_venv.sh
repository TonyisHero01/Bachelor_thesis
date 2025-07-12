#!/bin/bash

create_venv_and_install() {
  local SCRIPTS_DIR="$1"
  local VENV_PATH="$SCRIPTS_DIR/venv"
  local REQUIREMENTS_FILE="$SCRIPTS_DIR/requirements.txt"

  if [ -d "$SCRIPTS_DIR" ]; then
    echo "🧹 Removing old virtual environment at $VENV_PATH ..."
    rm -rf "$VENV_PATH"

    echo "🔧 Creating new Python virtual environment in $SCRIPTS_DIR ..."
    python3 -m venv "$VENV_PATH"

    if [ -f "$REQUIREMENTS_FILE" ]; then
      echo "📦 Installing Python dependencies from $REQUIREMENTS_FILE ..."
      "$VENV_PATH/bin/pip" install --upgrade pip
      "$VENV_PATH/bin/pip" install -r "$REQUIREMENTS_FILE"
    else
      echo "⚠️ Requirements file not found: $REQUIREMENTS_FILE"
    fi
  fi
}