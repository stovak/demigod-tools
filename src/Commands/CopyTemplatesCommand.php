<?php

namespace Pantheon\DemigodTools\Commands;

use Pantheon\DemigodTools\Utility\Crypt;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\TerminusHello\Model\Greeter;

/**
 * Say hello to the user
 */
class CopyTemplatesCommand extends TerminusCommand
{

  /**
   * Copy Templates
   *
   * @command demigod:copy-templates
   * @aliases dgct
   *
   * @param string $site_name
   *
   * @throws \Exception
   */
    public function copyTemplates(string $site_name)
    {
        $base_dir = dirname(__DIR__, 2);
        $clone_dir = $_SERVER['HOME'] . '/pantheon-local-copies/' . $site_name;
        if (!is_dir($clone_dir)) {
          throw new \Exception("TODO: clone this automatically if it doesn't exist.");
        }
        foreach ([
                   $clone_dir . '/web/sites/default/files/translations',
                   $clone_dir . '/web/sites/default/temp',
                   $clone_dir . '/web/sites/default/private',
                   $clone_dir . '/db',
                   $clone_dir . '/logs',
                 ] as $directory) {
          if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
          }
          touch($directory . "/.gitkeep");
        }

        $this->copyFrameworkFiles( $this->getFramework(), $site_name, $base_dir, $clone_dir );

        chdir($clone_dir);
        exec('echo ".idea\n.envrc\nlogs/*\ndb/*\n.DS_Store" >> .gitignore ');
        exec('direnv allow');
        if (php_uname("s") == "Darwin") {
          exec('brew bundle install');
        }

    }

    /**
     * Copy the template files based on the framework.
     *
     * @param string $framework The CMS framework identified by getFramework().
     * @param array ...$args Array of arguments required for copying files.
     *              $site_name The site name that was called into copyTemplates.
     *              $base_dir This plugin's base directory.
     *              $clone_dir The directory the site was cloned into.
     */
    private function copyFrameworkFiles( string $framework, ...$args ) {
      list( $site_name, $base_dir, $clone_dir ) = $args;
      $iterator = new \DirectoryIterator("$base_dir/templates/$framework");
      for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
          if (is_file($iterator->current()->getRealPath())) {
              switch ($iterator->current()->getFilename()) {
                  case 'settings.local.php':
                      copy(
                          $iterator->current()->getRealPath(),
                          $clone_dir . '/web/sites/default/settings.local.php'
                      );
                      break;

                  case '.envrc':
                      $contents = file_get_contents($iterator->current()->getRealPath());
                      $contents = str_replace('**PROJECT_NAME**', $site_name, $contents);
                      $contents = str_replace('**PROJECT_PATH**', $clone_dir, $contents);
                      file_put_contents($clone_dir . '/' . $iterator->current()->getFilename(), $contents);
                      break;

                  default:
                      copy(
                          $iterator->current()->getRealPath(),
                          $clone_dir . '/' . $iterator->current()->getFilename()
                      );
              }
          }
      }
    }

    /**
     * Get the site framework.
     *
     * @command demigod:get-framework
     *
     * @return string The site framework, pulled from site:info.
     */
    public function getFramework() : string {
      $output = [];
      exec( 'terminus site:info | grep Framework | xargs', $output );

      // If site:info didn't give us the output we expected, bail early.
      if ( empty( $output ) ) {
        return '❓ Could not determine site framework.';
      }

      $framework = str_replace( 'Framework ', '', $output[0] );

      return $framework;
    }
}
