<?php

namespace Drupal\mtnma_site\Plugin\Filter;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Points root-relative links at the page in the text's own language.
 *
 * Page bodies link to other pages as "/minister" in every translation; this
 * outputs "/ar/minister" in the Arabic text and "/fr/minister" in the French
 * one, using the target's alias in that language.
 */
#[Filter(
  id: 'mtnma_language_links',
  title: new TranslatableMarkup('Language-aware internal links'),
  description: new TranslatableMarkup('Adds the language prefix to links such as href="/contact".'),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
)]
class LanguageLinks extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult();
    $language = \Drupal::languageManager()->getLanguage($langcode);
    if (!$language || $langcode === LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      return $result->setProcessedText($text);
    }
    $prefixes = \Drupal::config('language.negotiation')->get('url.prefixes') ?? [];
    $text = preg_replace_callback('/\bhref="(\/(?!\/)[^"#?]*)([^"]*)"/', function (array $match) use ($language, $prefixes, $result) {
      [$attribute, $path, $suffix] = $match;
      $first = explode('/', $path)[1];
      // Leave links that already carry a prefix, and files.
      if (in_array($first, $prefixes, TRUE) || in_array($first, ['sites', 'themes', 'core', 'modules', 'system'], TRUE)) {
        return $attribute;
      }
      $url = Url::fromUserInput($path, ['language' => $language])->toString(TRUE);
      $result->addCacheableDependency($url);
      return 'href="' . $url->getGeneratedUrl() . $suffix . '"';
    }, $text);
    return $result->setProcessedText($text);
  }

}
