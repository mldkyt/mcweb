#!/bin/bash

if ! command -v go
then
    echo "Please install golang to continue"
    exit 1
fi

export DB_POSTGRES_HOST=localhost
export DB_POSTGRES_USER=lunya
export DB_POSTGRES_PASSWORD=lunya
export DB_POSTGRES_NAME=lunya

watchexec -e go,html,css,webp,jpg,png,mod,sum -r -- go run main.go
