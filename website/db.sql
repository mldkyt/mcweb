CREATE TABLE players (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    playtime INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE player_time_tracks (
    id SERIAL PRIMARY KEY,
    player TEXT NOT NULL,
    player_start_time TIMESTAMP NOT NULL
);

CREATE TABLE gallery_items (
    id SERIAL PRIMARY KEY,
    ip TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    file_name TEXT NOT NULL,
    description TEXT NOT NULL,
    user_name TEXT NOT NULL,
    approved BOOLEAN NOT NULL DEFAULT FALSE
);
