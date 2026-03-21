FROM golang:1.26-alpine AS build-server

WORKDIR /build

COPY . .

ENV GOCACHE=/root/.cache/go-build
RUN --mount=type=cache,target="/root/.cache/go-build" \
    --mount=type=cache,target="/go/pkg/mod" \
    go build

FROM alpine:latest AS final

WORKDIR /app

COPY --from=build-server /build/website .

ENTRYPOINT [ "/app/website" ]
