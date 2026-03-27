#!/usr/bin/env bash
set -euo pipefail

# === CONFIGURATION ===
DB="Avision"
USER="AvisionAdmin"
PASS="ZQDmdefhn@5x8@e8"
OUTDIR="/var/www/vhosts/avision.videosonic.fr/httpdocs/backups/mysql"
mkdir -p "$OUTDIR"

# === NOM DU FICHIER (1 SEUL DUMP CONSERVÉ) ===
FILE="$OUTDIR/${DB}_latest.sql.gz"

# === SUPPRESSION DE L'ANCIEN DUMP ===
rm -f "$OUTDIR"/*.sql.gz 2>/dev/null || true

# === EXPORT DE LA BASE ===
/usr/bin/mysqldump \
  --user="$USER" \
  --password="$PASS" \
  --host=localhost \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  "$DB" | gzip -9 > "$FILE"

# === VÉRIFICATION ===
if [ ! -s "$FILE" ]; then
  echo "❌ Erreur : le dump MySQL est vide ou a échoué !" >&2
  exit 1
fi

# === LOG OPTIONNEL ===
echo "✅ Dump MySQL réussi : $FILE ($(date '+%F %T'))"