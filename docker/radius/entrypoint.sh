#!/bin/sh
#
# Demarrage du serveur RADIUS TIKRAS.
# La base est partagee avec le panneau: les tickets qu'il enregistre sont
# authentifies ici, et les routeurs autorises sont lus dans la table nas.
set -e

BASE="/data/radius/radius.sqlite"
mkdir -p /data/radius

# Creation de la base au premier demarrage, a partir du schema fourni.
if [ ! -f "$BASE" ]; then
  echo "Initialisation de la base RADIUS"
  sqlite3 "$BASE" < /etc/raddb/mods-config/sql/main/sqlite/schema.sql
fi

# Table des routeurs autorises: absente du schema standard, elle permet
# d'activer un routeur depuis le panneau sans redemarrer le service.
sqlite3 "$BASE" "
CREATE TABLE IF NOT EXISTS nas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nasname TEXT NOT NULL,
  shortname TEXT,
  type TEXT DEFAULT 'other',
  ports INTEGER,
  secret TEXT NOT NULL,
  server TEXT,
  community TEXT,
  description TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS nas_nasname ON nas(nasname);
"

# L'application ecrit sous l'identite www-data (33) et le serveur RADIUS sous
# la sienne: le groupe commun leur donne l'acces en ecriture. Le dossier doit
# l'etre aussi, SQLite y creant ses fichiers de journal.
chown -R 33:33 /data/radius 2>/dev/null || true
chmod 775 /data/radius 2>/dev/null || true
chmod 664 "$BASE" 2>/dev/null || true

echo "Serveur RADIUS TIKRAS pret (base: $BASE)"
# Le binaire s'appelle freeradius dans cette image.
exec freeradius -f -l stdout "$@"
