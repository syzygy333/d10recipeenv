<?php

namespace Drupal\KernelTests\Core\Recipe;

use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeFileException;
use Drupal\Core\Recipe\RecipeMissingExtensionsException;
use Drupal\Core\Recipe\RecipePreExistingConfigException;
use Drupal\Core\Recipe\UnknownRecipeException;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\Core\Recipe\Recipe
 * @group Recipe
 */
class RecipeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field'];

  public function providerTestCreateFromDirectory(): array {
    return [
      'no extensions' => ['no_extensions', 'No extensions' , 'Testing', [], 'A recipe description'],
      // Filter is installed because it is a dependency and it is not yet installed.
      'install_two_modules' => ['install_two_modules', 'Install two modules' , 'Content type', ['filter', 'text', 'node'], ''],
    ];
  }

  /**
   * @dataProvider providerTestCreateFromDirectory
   */
  public function testCreateFromDirectory2(string $recipe_name, string $expected_name, string $expected_type, array $expected_modules, string $expected_description): void {
    $recipe = Recipe::createFromDirectory('core/tests/fixtures/recipes/' . $recipe_name);
    $this->assertSame($expected_name, $recipe->name);
    $this->assertSame($expected_type, $recipe->type);
    $this->assertSame($expected_modules, $recipe->install->modules);
    $this->assertSame($expected_description, $recipe->description);
  }

  public function testCreateFromDirectoryNoRecipe(): void {
    $this->expectException(RecipeFileException::class);
    $this->expectExceptionMessage('There is no core/tests/fixtures/recipes/no_recipe/recipe.yml file');
    Recipe::createFromDirectory('core/tests/fixtures/recipes/no_recipe');
  }

  public function testCreateFromDirectoryNoRecipeName(): void {
    $this->expectException(RecipeFileException::class);
    $this->expectExceptionMessage('The core/tests/fixtures/recipes/no_name/recipe.yml has no name value.');
    Recipe::createFromDirectory('core/tests/fixtures/recipes/no_name');
  }

  public function testCreateFromDirectoryMissingExtensions(): void {
    $this->enableModules(['module_test']);

    // Create a missing fake dependency.
    // dblog will depend on Config, which depends on a non-existing module Foo.
    // Nothing should be installed.
    \Drupal::state()->set('module_test.dependency', 'missing dependency');

    try {
      Recipe::createFromDirectory('core/tests/fixtures/recipes/missing_extensions');
      $this->fail('Expected exception not thrown');
    }
    catch (RecipeMissingExtensionsException $e) {
      $this->assertSame(['does_not_exist_one', 'does_not_exist_two', 'foo'], $e->extensions);
    }
  }

  public function testPreExistingDifferentConfiguration(): void {
    // Install the node module, its dependencies and configuration.
    $this->container->get('module_installer')->install(['node']);
    $this->assertFalse($this->config('node.settings')->get('use_admin_theme'), 'The node.settings:use_admin_theme is set to FALSE');

    try {
      Recipe::createFromDirectory('core/tests/fixtures/recipes/install_node_with_config');
      $this->fail('Expected exception not thrown');
    }
    catch (RecipePreExistingConfigException $e) {
      $this->assertSame("The configuration 'node.settings' exists already and does not match the recipe's configuration", $e->getMessage());
      $this->assertSame('node.settings', $e->configName);
    }
  }

  public function testPreExistingMatchingConfiguration(): void {
    // Install the node module, its dependencies and configuration.
    $this->container->get('module_installer')->install(['node']);
    // Change the config to match the recipe's config to prevent the exception
    // being thrown.
    $this->config('node.settings')->set('use_admin_theme', TRUE)->save();

    $recipe = Recipe::createFromDirectory('core/tests/fixtures/recipes/install_node_with_config');
    $this->assertSame('core/tests/fixtures/recipes/install_node_with_config/config', $recipe->config->recipeConfigDirectory);
  }

  public function testRecipeIncludeMissing(): void {
    try {
      Recipe::createFromDirectory('core/tests/fixtures/recipes/recipe_include_missing');
    }
    catch (UnknownRecipeException $e) {
      $this->assertSame('does_not_exist', $e->recipe);
      $this->assertSame(['core/tests/fixtures/recipes'], $e->searchPaths);
      $this->assertSame('Can not find the does_not_exist recipe, search paths: core/tests/fixtures/recipes', $e->getMessage());
    }
  }

}
