#!/bin/bash

spinner() {
  local pid=$1
  local delay=0.1
  local spinstr='|/-\'
  while kill -0 $pid 2>/dev/null; do
    local temp=${spinstr#?}
    printf " [%c]  " "$spinstr"
    local spinstr=$temp${spinstr%"$temp"}
    sleep $delay
    printf "\b\b\b\b\b\b"
  done
  printf "    \b\b\b\b"
}

# List of locales to create/update
LOCALES=(
	es_ES pt_BR fr_FR de_DE nl_NL it_IT pl_PL
)

# List of available GPT models for translation
GPT_MODELS=(
  "gpt-4.1-mini"
  "gpt-4o"
  "gpt-4o-mini"
  "gpt-4"
  "gpt-4-turbo"
  "gpt-3.5-turbo"
  "gpt-4.1"
  "gpt-4.1-nano"
  "gpt-4.5"
  "o4-mini"
  "o4-mini-high"
  "o3"
  "o3-mini"
  "o3-mini-high"
  "o1"
  "o1-mini"
  "o1-pro"
)


echo "❓ Do you want to run 'npm run i18n' to regenerate the POT file? [y/n]"
read -r answer

if [[ "$answer" =~ ^[Yy]$ ]]; then
  echo "🚀 Running textdomain update and POT file generation..."
  npm run i18n & spinner $!
  I18N_EXIT_CODE=${PIPESTATUS[0]}
  if [[ $I18N_EXIT_CODE -ne 0 ]]; then
    echo "❌ 'npm run i18n' failed. Stopping further execution."
    exit 1
  else
    echo "✅ 'npm run i18n' completed successfully."
  fi
else
  echo "⏩ Skipping 'npm run i18n' and proceeding to the next steps."
fi

echo "📄 Creating or updating PO files for locales..."
for locale in "${LOCALES[@]}"; do
  PO_FILE="languages/openwp-$locale.po"
  POT_FILE="languages/openwp.pot"

  if [ ! -f "$PO_FILE" ]; then
    echo "🆕 Creating $PO_FILE from $POT_FILE"
    cp "$POT_FILE" "$PO_FILE"
  else
    echo "✅ $PO_FILE exists, skipping creation"
  fi
done

# Ask whether to translate all languages
echo "❓ Do you want to translate all languages listed? [y/n]"
read -r translate_all

declare -a TRANSLATE_LOCALES=()

if [[ "$translate_all" =~ ^[Yy]$ ]]; then
  TRANSLATE_LOCALES=("${LOCALES[@]}")
else
  echo "📝 Available languages:"
  for i in "${!LOCALES[@]}"; do
    echo "  $((i+1)). ${LOCALES[$i]}"
  done
  echo "❓ Enter the numbers of the languages you want to translate, separated by spaces:"
  read -r -a selected_indices

  for idx in "${selected_indices[@]}"; do
    if (( idx >= 1 && idx <= ${#LOCALES[@]} )); then
      TRANSLATE_LOCALES+=("${LOCALES[$((idx-1))]}")
    else
      echo "⚠️ Warning: Invalid selection $idx ignored."
    fi
  done
fi

# Select GPT model
echo "🤖 Select which GPT model to use for translation:"
for i in "${!GPT_MODELS[@]}"; do
  echo "  $((i+1)). ${GPT_MODELS[$i]}"
done
echo "❓ Enter the number of the model (default: 1):"
read -r selected_model_idx
if [[ -z "$selected_model_idx" || ! "$selected_model_idx" =~ ^[0-9]+$ || "$selected_model_idx" -lt 1 || "$selected_model_idx" -gt "${#GPT_MODELS[@]}" ]]; then
  selected_model_idx=1
fi
SELECTED_MODEL="${GPT_MODELS[$((selected_model_idx-1))]}"
echo "➡️ Using GPT model: $SELECTED_MODEL"

if [ ${#TRANSLATE_LOCALES[@]} -eq 0 ]; then
  echo "⚠️ No languages selected for translation. Skipping GPT-PO translation step."
else
  echo "🔄 Updating PO files before translation..."
  npm run i18n:po & spinner $!

  echo "🤖 Translating selected PO files using GPT-PO and model $SELECTED_MODEL..."
  for locale in "${TRANSLATE_LOCALES[@]}"; do
    LANG_CODE=$(echo "$locale" | cut -d'_' -f1)
    PO_FILE="languages/openwp-$locale.po"

    if [ -f "$PO_FILE" ]; then
      echo "🌐 Translating $PO_FILE for language $LANG_CODE"
      npx gpt-po translate --po "$PO_FILE" --lang "$LANG_CODE" --model "$SELECTED_MODEL" --verbose & spinner $!
    else
      echo "⚠️ Warning: $PO_FILE not found, skipping GPT-PO translation"
    fi
  done

  echo "🔄 Updating PO files again after translations..."
  npm run i18n:po & spinner $!
fi

echo "🛠️ Generating MO files..."
npm run i18n:mo & spinner $!

echo "📦 Generating JSON translation files..."
npm run i18n:json & spinner $!

echo "🎉 All commands executed successfully."
