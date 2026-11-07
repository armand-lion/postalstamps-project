import json
import re
from collections import defaultdict
from pathlib import Path

LANGUAGES = ["nl", "en", "de", "fr", "es"]

SEARCH_DIRECTORIES = [
    Path("resources/views"),
    Path("app"),
]

# Herkent het begin van een Laravel-vertaalfunctie.
FUNCTION_PATTERN = re.compile(r"(?<![\w])(?:__|trans)\s*\(")

# Herkent een PHP-string als eerste argument.
STRING_PATTERN = re.compile(
    r"""(?P<quote>['"])(?P<value>(?:\\.|(?!\1).)*?)\1""",
    re.DOTALL,
)

keys = defaultdict(set)


def extract_translation_keys(content):
    """Verzamel statische vertaalkeys uit PHP- en Blade-code."""

    for match in FUNCTION_PATTERN.finditer(content):
        position = match.end()

        # Witruimte na het openingshaakje overslaan.
        while position < len(content) and content[position].isspace():
            position += 1

        string_match = STRING_PATTERN.match(content, position)

        if not string_match:
            continue

        key = string_match.group("value")

        # PHP-escaped aanhalingstekens normaliseren.
        key = key.replace("\\'", "'").replace('\\"', '"')

        # Controleer of het eerste argument correct eindigt.
        end_position = string_match.end()

        while (
            end_position < len(content)
            and content[end_position].isspace()
        ):
            end_position += 1

        if end_position >= len(content):
            continue

        # Na de vertaalkey moet een komma of sluithaakje staan.
        if content[end_position] not in [",", ")"]:
            continue

        # Geen lege vertaalkeys verwerken.
        if not key.strip():
            continue

        # Regelnummer bepalen.
        line_number = content.count("\n", 0, match.start()) + 1

        yield key, line_number


# Alle Blade- en PHP-bestanden doorzoeken.
for directory in SEARCH_DIRECTORIES:
    if not directory.exists():
        continue

    for file in directory.rglob("*.php"):
        content = file.read_text(encoding="utf-8")

        for key, line_number in extract_translation_keys(content):
            keys[key].add(f"{file}:{line_number}")


print("=" * 60)
print("CENTRALE CONTROLE LARAVEL-VERTALINGEN")
print("=" * 60)

print(f"\nAantal unieke vertaalkeys: {len(keys)}")

total_missing = 0

for language in LANGUAGES:
    path = Path(f"lang/{language}.json")

    print(f"\n{'=' * 60}")
    print(f"TAAL: {language.upper()}")
    print("=" * 60)

    if not path.exists():
        print("Vertaalbestand ontbreekt!")
        continue

    translations = json.loads(path.read_text(encoding="utf-8"))

    missing = [
        key for key in sorted(keys)
        if key not in translations
        or not isinstance(translations[key], str)
        or not translations[key].strip()
    ]

    total_missing += len(missing)

    print(f"Ontbrekende vertalingen: {len(missing)}")

    for key in missing:
        print(f"\n- {key}")

        for location in sorted(keys[key]):
            print(f"  Bestand: {location}")

print(f"\n{'=' * 60}")
print("SAMENVATTING")
print("=" * 60)

print(f"Unieke vertaalkeys: {len(keys)}")
print(f"Totaal ontbrekende vertalingen: {total_missing}")

if total_missing == 0:
    print("Alle gecontroleerde vertalingen zijn aanwezig.")
else:
    print("Er zijn nog vertalingen die aangevuld moeten worden.")
