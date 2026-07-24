
FROM python:3.14-alpine

RUN apk add --no-cache gcc postgresql17 postgresql17-dev
RUN pip install -U uv

WORKDIR /app

COPY . .

RUN --mount=type=cache,target="/root/.cache/uv" uv sync

ENTRYPOINT [ "uv", "run", "main.py" ]
