<?php

namespace Drupal\domain_simple_sitemap\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Class DomainSimpleSitemapConfigForm.
 *
 * @package Drupal\domain_simple_sitemap\Form
 */
class DomainSimpleSitemapConfigForm extends ConfigFormBase {

  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('config.factory')
    );
  }

  protected function getEditableConfigNames() {
    return [
      'domain_simple_sitemap.settings',
    ];
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_simple_sitemap_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('domain_simple_sitemap.settings');
	$form['domain_simple_sitemap_filter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use node source instead of node access as filter'),
      '#default_value' => $config->get('domain_simple_sitemap_filter'),
      '#description' => $this->t('When checked the Domain Sitemap will be filtered by domain source.'),
    ];
       return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
     $this->config('domain_simple_sitemap.settings')
      ->set('domain_simple_sitemap_filter', (bool) $form_state->getValue('domain_simple_sitemap_filter'))
      ->save();

    parent::submitForm($form, $form_state);
	$config = $this->config('domain_simple_sitemap.settings');
	$config->save();
  }
}