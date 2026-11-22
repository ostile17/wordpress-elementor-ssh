#!/usr/bin/env bash
#
# Aggiunge una lezione al diario di bordo della skill, da qualunque progetto.
#
# Lo script si ricava da solo la cartella della skill (`dirname $0`), quindi non
# c'e' nessun percorso da sapere e nessuna variabile da impostare: funziona anche
# se la skill viene spostata o installata su un'altra macchina.
#
# USO
#   lezione.sh "Titolo" "Nome progetto" <<'FINE'
#   Cosa credevo, cosa succedeva davvero, come si riconosce, come si rimedia.
#   FINE
#
# Il corpo arriva dallo standard input invece che come argomento: cosi' puo'
# contenere virgolette, accenti, righe multiple e blocchi di codice senza dover
# combattere con l'escape della shell.

set -euo pipefail

titolo="${1:-}"
progetto="${2:-}"

if [ -z "$titolo" ]; then
    echo "Serve almeno il titolo." >&2
    echo "  lezione.sh \"Titolo\" \"Progetto\" <<'FINE'" >&2
    echo "  ...corpo..." >&2
    echo "  FINE" >&2
    exit 1
fi

cartella="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
diario="$cartella/references/diario.md"

if [ ! -f "$diario" ]; then
    echo "Diario non trovato in $diario" >&2
    exit 1
fi

corpo="$(cat)"
if [ -z "${corpo// }" ]; then
    echo "Il corpo e' vuoto: una voce senza il 'cosa credevo' non serve a nessuno." >&2
    exit 1
fi

# Alla prima voce sparisce il segnaposto, altrimenti resta li' a dire il falso.
if grep -q '^\*(nessuna' "$diario"; then
    tmp="$(mktemp)"
    grep -v '^\*(nessuna' "$diario" > "$tmp"
    mv "$tmp" "$diario"
fi

{
    printf '\n## %s\n\n' "$titolo"
    printf '**Progetto:** %s — **Data:** %s\n\n' "${progetto:-non indicato}" "$(date +%F)"
    printf '%s\n' "$corpo"
} >> "$diario"

# Le voci sono i titoli di secondo livello dopo la riga "# Voci".
voci=$(awk '/^# Voci$/{via=1; next} via && /^## /{n++} END{print n+0}' "$diario")

echo "Aggiunta al diario: $diario"
echo "Voci in attesa di essere sistemate: $voci"

if [ "$voci" -ge 10 ]; then
    echo
    echo "Sono dieci o piu': oltre questa soglia il diario smette di essere letto."
    echo "Riordinale nei file di riferimento (le corrispondenze sono in diario.md)"
    echo "e toglile da qui."
fi
