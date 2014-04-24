<?php

/**
 *  --------------------------------------------------------------
 *  DESCRIPTION
 *  --------------------------------------------------------------
 * 
 *  This `Settings` class loads and parses a settings file 
 *  which looks something like this:
 * 
 *  # Start of file
 *
 *      # A foo entry.
 *      foo = bar
 *
 *          # These are subproperties of foo.bar
 *          biz = baz
 *          boo = beep
 *
 *      # Another foo entry.
 *      foo = bang
 *
 *          # These are subproperties of foo.bang
 *          biz = bizzaz
 *          boo = beepaz
 *
 *      # Comments start with the hash character (#).
 *      fi = fum
 *
 *  # End of file
 *
 *  This class can take that file and generate this array:
 *
 *      array(
 *          'foo' => array(
 *              'bar' => array(
 *                  'biz' => 'baz',
 *                  'boo' => 'beep',
 *              ),
 *              'bang' => array(
 *                  'biz' => 'bizzaz',
 *                  'boo' => 'beepaz',
 *              ),
 *          ),
 *          'fi' => 'fum',
 *      );
 *
 *  --------------------------------------------------------------
 *  USAGE
 *  --------------------------------------------------------------
 *
 *  Instantiate the class;
 *      $settings_objet = new Settings();
 *
 *  Try to load a file called 'settings.local', or if that doesn't
 *  work, try to load 'settings.default':. 
 *      $settings_object->load('settings.local', 'settings.default');
 *
 *  Get all settings as one big array:
 *      $settings = $settings_object->all();
 *
 *  Get a specific setting, e.g., foo.bang.biz from above:
 *      $foo = $settings_object->get('foo', 'bang', 'biz');
 * 
 *
 *  @author JT Paasch
 *  @email  jt dot paasch at gmail dot com
 *  @license MIT <http://opensource.org/licenses/MIT>
 */
class Settings {

    /**
     *  All settings data will be stored here.
     *
     *  @access private
     *  @var Array
     */
    private $settings = array();

    /**
     *  This method fetches all settings data and returns the whole lot.
     *
     *  @access public
     *  @return Array All settings.
     */
    public function all() {
        return $this->settings;
    }

    /**
     *  This method fetches a value from the settings data. You can 
     *  pass in any number of keys as arguments, e.g.:
     *
     *    $this->get('database', 'office', 'host', ...)
     *
     *  This method will then try to walk the settings tree with those
     *  keys. For instance, the above call would look for 
     *  $this->settings['database']['office']['host']. 
     *  If any of the keys don't exist, a RuntimeException is thrown.
     *
     *  @access public
     *  @param String $key1 A key to look for in the settings data.
     *  @param String $key2 (optional) Another key to look for.
     *  @param String $key3 (optional) ... etc.
     *  @throws RuntimeException if any key does not exist.
     *  @return Array or String The found settings data.
     */
    public function get() {

        // Get all arguments passed in.
        $keys = func_get_args();

        // Try to walk the settings tree with those keys.
        $cursor = $this->all();
        foreach ($keys as $key) {

            // If the key exists, set the cursor to this position.
            if (isset($cursor[$key])) {
                $cursor = $cursor[$key];
            }

            // If not, throw an exception.
            else {
                $all_keys = implode (':', $keys);
                $message = 'No setting exists for ' . $all_keys;
                throw new \RuntimeException($message);
            }

        }

        // Send back whatever we found.
        return $cursor;

    }

    /**
     *  This method loads a settings file. You can pass in 
     *  any number of files as arguments, e.g.,
     *
     *    $this->load('settings.conf', 'settings.conf.default', ...)
     *
     *  and this method will try to load the first file, then if that 
     *  fails, it will try to load the second file, and so on. If no
     *  files get loaded, it will throw an exception.
     *
     *  @access public
     *  @param String $file1 The path to a file to try.
     *  @param String $file2 (optional) The path to another file to try.
     *  @param String $file3 (optional) ... etc.
     *  @throws RuntimeExecption if no file is loaded.
     *  @return void
     */
    public function load() {

        // Get all arguments passed in.
        $filenames = func_get_args();

        // Try to load each one.
        foreach ($filenames as $filename) {

            // Try to open the file. If we can't do it, try the next one.
            try {
                $file = new \SplFileObject($filename);
            } catch (\Exception $exception) {
                error_log('Could not open: ' . $filename);
                continue;
            }

            // Otherwise, we got it.
            break;

        }

        // If we loaded the file, parse it.
        if (!empty($file)) {
            $this->settings = $this->parse($file);
        }

        // Otherwise, throw an exception.
        else {
            throw new \RuntimeException("No settings file loaded.");
        }

    }

    /**
     *  This method parses a settings file.
     *
     *  @access private
     *  @param String $file The contents of a file to parse.
     *  @return Array The parsed settings.
     */
    private function parse(\SplFileObject $file) {

        // We'll store our settings here:
        $settings = array();

        // We'll store our AST of the file here:
        $ast = $this->get_ast($file);

        // Now we can parse all top level items (the children
        // will get parsed recursively). 
        foreach ($ast as $index => $item) {
            if ($item['level'] === 1) {
                $this->process_ast_item($ast, $index, $settings);
            }
        }

        // Return the settings.
        return $settings;

    }

    /**
     *  This method processes one item in an AST.
     *
     *  @access private
     *  @param Array $ast A list of AST items.
     *  @param Integer $i The index of the AST item to process.
     *  @param Array &$settings The array to add parsed data to.
     *  @return void
     */
    private function process_ast_item($ast, $i, &$settings) {

        // Get a reference to this item and the next (for convenience).
        $this_item = $ast[$i];
        if (count($ast) - 1 > $i) {
            $next_item = $ast[$i + 1];
        } else {
            $next_item = array(
                'level' => $this_item['level'],
            );
        }

        // Get the key and value for this item. 
        $key = $this_item['key'];
        $value = $this_item['value'];

        // If the next item has a higher indentation level, it's a child.
        $has_child = $next_item['level'] > $this_item['level'];

        // If this item has no child, we can just add the key/value pair.
        if (!$has_child) {
            $settings[$key] = $value;
        }

        // Otherwise, we need to process the children.
        else {

            // Make sure there's container for the parent item.
            if (empty($settings[$key])) {
                $settings[$key] = array();
            }

            // And make sure there's a container for the children.
            if (empty($settings[$key][$value])) {
                $settings[$key][$value] = array();
            }

            // We're going to find all children and store them here:
            $children = array();

            // All items in the AST that have a larger indentation level
            // are children. Let's find all subsequent children.
            $continue = true;
            $counter = $i;
            while ($continue) {
                if (!isset($ast[$counter + 1])) {
                    break;
                }
                if ($ast[$counter + 1]['level'] > $this_item['level']) {
                    $children[] = $ast[$counter + 1];
                    $counter += 1;
                } else {
                    $continue = false;
                }
            }

            // Now that we have a list of the chlidren, process each.
            // (Note that this is recursive.)
            foreach ($children as $index => $child) {
                if ($child['level'] === $this_item['level'] + 1) {
                    $this->process_ast_item(
                        $children, 
                        $index, 
                        $settings[$key][$value]
                    );
                }
            }

        }

    }

    /**
     *  This method walks a file, line by line, and it build a basic
     *  Abstract Syntax Tree (AST) from it. If there's bad indentation
     *  in the file, it throws an exception.
     *
     *  @access private
     *  @param SplFileObject $file The file to walk.
     *  @throws RuntimeException if there's bad indentation.
     *  @return Array The AST.
     */
    private function get_ast($file) {

        // We'll build up the AST here;
        $ast = array();

        // What indentation level are we at?
        $indentation_level = 0;

        // Process each line in turn.
        foreach ($file as $line_num => $line) {

            // Ignore empty lines.
            $trimmed_line = trim($line);
            if (empty($trimmed_line)) {
                continue;
            }

            // How many spaces until the first character?
            $spaces = 0;
            while ($line[$spaces] == ' ') {
                $spaces += 1;
            }

            // Indentation MUST come in 4 space blocks. 
            if ($spaces % 4 !== 0) {
                $message = 'Settings file: bad indentation on line ' 
                         . $line_num 
                         . '.';
                throw new \RuntimeException($message);
            }

            // Ignore comments.
            if ($line[$spaces] === '#') {
                continue;
            }

            // Get the key/value pair in the line.
            list($key, $value) = $this->get_key_value_pair($line);

            // Add a note to the AST.
            $ast[] = array(
                'level' => $spaces / 4,
                'key' => $key,
                'value' => $value,
            );

        }

        // Send it back.
        return $ast;

    }

    /**
     *  Get the key and the value from a "foo = bar" style line of text.
     *
     *  @access private
     *  @param String $line The line to parse.
     *  @return Array with $key and $value items.
     */
    private function get_key_value_pair($line) {
        $parts = explode('=', $line);
        $key = trim($parts[0]);
        $value = null;
        if (count($parts) > 1) {
            $value = trim($parts[1]);
        }
        return array($key, $value);
    }

}