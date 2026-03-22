#!/usr/bin/bash

if ! command -v uv >/dev/null 2>&1
then
  echo "Please install uv to continue"
  exit 1
fi

uv sync

export DATABASE_URL=postgresql://lunya:lunya@localhost:5432/lunya

watchexec -e py -r -- uv run main.py
