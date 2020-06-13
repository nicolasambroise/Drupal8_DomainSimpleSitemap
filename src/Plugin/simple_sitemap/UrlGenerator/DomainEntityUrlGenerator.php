<?php

namespace Drupal\domain_simple_sitemap\Plugin\simple_sitemap\UrlGenerator;
use Drupal\Core\Menu\MenuLinkBase;
use Drupal\Core\Url;
use Drupal\domain\Entity\Domain;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\EntityUrlGenerator;

/**
 * Class DomainEntityUrlGenerator
 *
 * @UrlGenerator(
 *   id = "domain_entity",
 *   label = @Translation("Domain entity URL generator"),
 *   description = @Translation("Generates URLs for entity bundles and bundle overrides."),
 * )
 */
class DomainEntityUrlGenerator extends EntityUrlGenerator {

  /**
   * @inheritdoc
   */
  public function getDataSets() {
    $data_sets = [];
    $sitemap_entity_types = $this->entityHelper->getSupportedEntityTypes();

    foreach ($this->generator->setVariants($this->sitemapVariant)->getBundleSettings() as $entity_type_name => $bundles) {
		if (isset($sitemap_entity_types[$entity_type_name])) {
			// Skip this entity type if another plugin is written to override its generation.
			foreach ($this->urlGeneratorManager->getDefinitions() as $plugin) {
				if (isset($plugin['settings']['overrides_entity_type'])
					&& $plugin['settings']['overrides_entity_type'] === $entity_type_name) {
					continue 2;
				}
			}

			$entityTypeStorage = $this->entityTypeManager->getStorage($entity_type_name);	
			$keys = $sitemap_entity_types[$entity_type_name]->getKeys();

			foreach ($bundles as $bundle_name => $bundle_settings) {
				// Skip if "Content type" is excluded for selected Varient (e.g. Index entities of this type in /admin/structure/types/manage/page)
				if (!empty($bundle_settings['index'])) {
					$query = $entityTypeStorage->getQuery();
				  
					if (empty($keys['id'])) {
						$query->sort($keys['id'], 'ASC');
					}
					if (!empty($keys['bundle'])) {
						$query->condition($keys['bundle'], $bundle_name);
					}
					if (!empty($keys['status'])) {
						$query->condition($keys['status'], 1);
					}

					$ActiveId = \Drupal::service('domain.negotiator')->getActiveId();	
					$DOMAIN_SOURCE = "field_domain_source";										   
			 
					if (!empty($keys['bundle'])) {
						if($keys['bundle'] == 'type' && $entity_type_name == 'node'){
							// Checkbox on "./admin/config/domain/domain_simple_sitemap/config"
							$source_only = \Drupal::config('domain_simple_sitemap.settings')->get('domain_simple_sitemap_filter');
							if($source_only){
								// Filter by Node Domain source
								$query->condition($DOMAIN_SOURCE . '.target_id', $ActiveId);
							}
							else{
								// Filtered by Node Domain access
								$orGroupDomain = $query->orConditionGroup()
								->condition(DOMAIN_ACCESS_FIELD . '.target_id', $ActiveId)
								->condition(DOMAIN_ACCESS_ALL_FIELD, 1);
								$query->condition($orGroupDomain);
							} 	 
							foreach ($query->execute() as $entity_id) {
								$data_sets[] = [
									'entity_type' => $entity_type_name,
									'id' => $entity_id,
									'domain_source' => $ActiveId,	
								];
							}
						}
						elseif($keys['bundle'] == 'vid' && $entity_type_name == 'taxonomy_term'){
						   // For taxonomy
						   foreach ($query->execute() as $entity_id) {
								$data_sets[] = [
									'entity_type' => $entity_type_name,
									'id' => $entity_id,
									'domain_source' => $ActiveId,		 
								];
							}
						}
					}
				}
			} // end foreach
		}
    }
    return $data_sets;
  }

  /**
   * @inheritdoc
   */
  protected function processDataSet($data_set) {

	$entity_id = $data_set['id']; //  = $entity->id();		
	$entity_type_name = $data_set['entity_type']; //  = $entity->getEntityTypeId();
	$entity_source = $data_set['domain_source'];

	if (empty($entity = $this->entityTypeManager->getStorage($entity_type_name)->load($entity_id))) {
      return FALSE;
    }

	// Remove Entity of other sources
	if($this->sitemapVariant != $entity_source){
		return FALSE;
	}										 

    $entity_settings = $this->generator
      ->setVariants($this->sitemapVariant)
      ->getEntityInstanceSettings($entity_type_name, $entity_id);

	if (empty($entity_settings['index'])) {
		return FALSE;
    }

     // Domain & URL variables
	$url_object = $entity->toUrl();
	$url_object->setOption('absolute', TRUE);

    // Do not include external paths.
    if (!$url_object->isRouted()) {
      return FALSE;
    }

    $path = $url_object->getInternalPath();
    $url_object->setOption('absolute', TRUE);
    $domain = \Drupal::service('domain.negotiator')->getActiveDomain();
	
	// Photos
	$url_photo = [];
	$url_photo_formatted = "";
	if(!empty($entity_settings['include_images'])){
       $url_photo = $this->getImages($this->sitemapVariant,$entity_type_name, $entity_id);
	   if(!empty($url_photo)){
		   $url_photo_formatted = Url::fromUri($url_photo);
	   }
	}
	 
    return [
      'url' => $url_object,
      'lastmod' => method_exists($entity, 'getChangedTime') ? date_iso8601($entity->getChangedTime()) : NULL,
      'priority' => isset($entity_settings['priority']) ? $entity_settings['priority'] : NULL,
      'changefreq' => !empty($entity_settings['changefreq']) ? $entity_settings['changefreq'] : NULL,
      'images' => !empty($url_photo_formatted)? $url_photo_formatted: [],

      // Additional info useful in hooks.
      'meta' => [
        'path' => $path,
        'entity_info' => [
          'entity_type' => $entity_type_name,
          'id' => $entity_id,
        ],
      ]
    ];
  }

  /**
   * @param string $sitemap_variant
   * @return $this
   */
 public function setSitemapVariant($sitemap_variant) {
    parent::setSitemapVariant($sitemap_variant);
	$domain = Domain::load($sitemap_variant); // Load domain
    \Drupal::service('domain.negotiator')->setActiveDomain($domain);
    return $this;
  }
  
  /**
   * @param string $sitemap_variant, $entity_type_name, $entity_id
   * @return string $url_photo 
   */
  protected function getImages($current_variant, $entity_type_name, $entity_id){
	$term_obj = $this->entityTypeManager->getStorage($entity_type_name)->load($entity_id);
	// TODO : change "field_photo" by something not specific
	if (!empty($term_obj)){
		if ($term_obj->hasField('field_image') && isset($term_obj->get('field_image')->entity)){
			$url_photo = file_create_url($term_obj->get('field_image')->entity->getFileUri());
			return $url_photo;
		}
		elseif($term_obj->hasField('field_photo') && isset($term_obj->get('field_photo')->entity)){
			$url_photo = file_create_url($term_obj->get('field_photo')->entity->getFileUri());
			return $url_photo;
		}
		elseif($term_obj->hasField('field_img') && isset($term_obj->get('field_img')->entity)){
			$url_photo = file_create_url($term_obj->get('field_img')->entity->getFileUri());
			return $url_photo;
		}
	}
	return "";
  }

  /**
   * @param string $url
   * @return string
   */
  protected function replaceBaseUrlWithCustom($url) {
    $domain = \Drupal::service('domain.negotiator')->getActiveDomain();
    return str_replace($GLOBALS['base_url'], $domain->getScheme() . $domain->getCanonical(), $url);
  }
}