import datetime
import time
from os import getenv
from symtable import Class

from mcstatus import JavaServer
from mcstatus.responses import JavaStatusPlayer, JavaStatusPlayers
from sqlalchemy import Table, Column, VARCHAR, BIGINT, MetaData, create_engine, select, insert, DateTime, delete, \
    update, Integer, Boolean

md = MetaData()

players = Table(
    "players",
    md,
    Column("id", Integer, primary_key=True),
    Column("created_at", DateTime),
    Column("updated_at", DateTime, nullable=True),
    Column("deleted_at", DateTime, nullable=True),
    Column("name", VARCHAR),
    Column("playtime", Integer)
)

player_time_tracks = Table(
    "player_time_tracks",
    md,
    Column("player", VARCHAR, primary_key=True),
    Column("player_start_time", DateTime)
)

engine = create_engine(getenv("DATABASE_URL"))

def player_joined(player: JavaStatusPlayer):
    print("+", player.name, "joined")
    with engine.begin() as conn:
        conn.execute(
            insert(player_time_tracks)
            .values(player=player.name,
                    player_start_time=datetime.datetime.now(datetime.timezone.utc))
        )

        if conn.execute(
            select(players)
            .where(players.c.name == player.name)
        ).fetchone() is None:
            conn.execute(
                insert(players)
                .values(name=player.name,
                        created_at=datetime.datetime.now(datetime.timezone.utc),
                        playtime=0)
            )



def player_left(player: str):
    print("-", player, "left")
    with engine.begin() as conn:
        join_time = conn.execute(
            select(player_time_tracks.c.player_start_time)
            .where(player_time_tracks.c.player == player)
        ).fetchone()

        if join_time is None:
            raise ValueError("Player left but not in DB???")

        conn.execute(
            delete(player_time_tracks)
            .where(player_time_tracks.c.player == player)
        )

        player_data = conn.execute(
            select(players.c.playtime)
            .where(players.c.name == player)
        ).fetchone()

        playtime = datetime.datetime.now(datetime.timezone.utc) - join_time[0]

        conn.execute(
            update(players)
            .values(playtime=player_data[0] + playtime.total_seconds())
            .where(players.c.name == player)
        )


def check_players(players: JavaStatusPlayers):
    # Check if joined
    with engine.begin() as con:
        for player in (players.sample or []):
            db_player = con.execute(
                select(player_time_tracks).where(player_time_tracks.c.player == player.name)).fetchone()
            if not db_player:
                player_joined(player)

        db_players = [i[0] for i in con.execute(select(player_time_tracks.c.player)).fetchall()]
        for db_player in db_players:
            for player in (players.sample or []):
                if player.name == db_player:
                    break
            else:
                player_left(db_player)


def main():
    print("Looking up lunya.pet")
    status = JavaServer.lookup("lunya.pet").status()

    if status.players is None:
        print("Players missing from public data")
        return

    if status.players.sample is None and status.players.online > 0:
        print("Players missing from public data")
        return

    check_players(status.players)


if __name__ == "__main__":
    while True:
        main()
        time.sleep(10)
