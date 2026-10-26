<?php

namespace Drupal\mtnma_site\Hook;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Site search: results in the visitor's language, Arabic-aware matching.
 */
class SearchHooks {

  public function __construct(
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Implements hook_query_TAG_alter() for the content search query.
   *
   * Content is indexed once per translation; only the translation in the
   * current language is listed, so a French visitor gets French pages.
   */
  #[Hook('query_search_node_search_alter')]
  public function querySearchNodeSearchAlter(AlterableInterface $query): void {
    $query->condition('i.langcode', $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId());
  }

  /**
   * Implements hook_search_preprocess().
   *
   * Runs on indexed text and on search keywords alike. Arabic is written with
   * optional short vowels and several spellings of alef, and the article and
   * common prepositions are attached to the word ("الخدمات", "بالخدمات"); the
   * index stores one plain form, so "خدمات" finds "الخدمات الرقمية".
   */
  #[Hook('search_preprocess')]
  public function searchPreprocess(string $text, ?string $langcode = NULL): string {
    if (!preg_match('/\p{Arabic}/u', $text)) {
      return $text;
    }
    // Short vowels, shadda, sukun, dagger alef and tatweel.
    $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
    $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي']);
    // Attached article and prepositions, when at least three letters remain.
    return preg_replace('/(?<![\p{Arabic}])(?:وال|فال|بال|كال|لل|ال)(?=\p{Arabic}{3})/u', '', $text);
  }

}
