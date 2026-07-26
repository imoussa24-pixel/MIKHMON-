# Serveur RADIUS TIKRAS IT.
# Authentifie les tickets a partir de la base partagee avec le panneau, en
# remplacement de User Manager.
FROM freeradius/freeradius-server:3.2.7

USER root

RUN apt-get update \
    && apt-get install -y --no-install-recommends sqlite3 \
    && rm -rf /var/lib/apt/lists/*

# La base est partagee avec l'application, qui tourne sous www-data (33).
# SQLite doit pouvoir ecrire (journal, verrous) meme pour une simple lecture:
# le compte du serveur RADIUS rejoint donc ce groupe.
RUN (getent group www-data || groupadd -g 33 www-data) \
    && usermod -aG www-data freerad

# Module SQL adosse a SQLite et site dedie.
COPY mods-sql /etc/raddb/mods-available/sql
COPY site-tikras /etc/raddb/sites-available/default
COPY clients.conf /etc/raddb/clients.conf
COPY entrypoint.sh /usr/local/bin/tikras-radius

# Le module EAP et son site interne servent au 802.1X, pas au Hotspot
# MikroTik: les retirer evite de charger une configuration inutile.
RUN ln -sf /etc/raddb/mods-available/sql /etc/raddb/mods-enabled/sql \
    && chmod +x /usr/local/bin/tikras-radius \
    && rm -f /etc/raddb/mods-enabled/eap \
    && rm -f /etc/raddb/sites-enabled/inner-tunnel

EXPOSE 1812/udp 1813/udp

ENTRYPOINT ["/usr/local/bin/tikras-radius"]
