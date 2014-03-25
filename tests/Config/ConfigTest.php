<?php

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;

use org\bovigo\vfs\vfsStream;


class ConfigTest extends \PHPUnit_Framework_TestCase {

  private $test_config = ['t' => 't', 'x' => 123, 'a' => ['t' => 't', 'x' => 1234]];

  /**
   * vsfStream root dir
   */
  private $fs_root;

  private $parser;
  private $dumper;


  protected function setUp() {
    
    $this->parser = new Parser;
    $this->dumper = new Dumper;

    $this->fs_root = vfsStream::setup('root');
  }

  public function testAddDataSource() {

    $config = $this->getMockBuilder('Dbmedialab\Drupal\Deploy\Modulefetch\Config\Config')
      ->setMethods(['__construct', 'save', 'load'])
      ->disableOriginalConstructor()
      ->getMock();

    $config->addDataSource('test', 'non-existant');

    $this->assertAttributeContains('non-existant', 'data', $config);
  }

  /**
   * @expectedException InvalidArgumentException
   */
  public function testLoadFail() {
    $config = $this->getMockBuilder('Dbmedialab\Drupal\Deploy\Modulefetch\Config\Config')
      ->setMethods(['__construct', 'save'])
      ->disableOriginalConstructor()
      ->getMock();

    $config->load('fail');
  }

  /**
   * @expectedException InvalidArgumentException
   */
  public function testLoadFailWithSource() {
    $config = $this->getMockBuilder('Dbmedialab\Drupal\Deploy\Modulefetch\Config\Config')
      ->setMethods(['__construct', 'save'])
      ->disableOriginalConstructor()
      ->getMock();

    $config->addDataSource('fail', 'non-existant');
    $config->load('fail');
  }

  public function testLoad() {

    $config = $this->getMockBuilder('Dbmedialab\Drupal\Deploy\Modulefetch\Config\Config')
      ->setConstructorArgs([$this->parser, $this->dumper])
      ->setMethods(['save'])
      ->getMock();

    vfsStream::newFile('config')
      ->at($this->fs_root)
      ->setContent($this->dumper->dump($this->test_config));

    $config->addDataSource('config', vfsStream::url('root/config'));
    $data = $config->load('config');

    $this->assertSame($this->test_config, $data);
  }

  /**
   * @expectedException InvalidArgumentException
   */
  public function testSaveFail() {
    $config = $this->getMockBuilder('Dbmedialab\Drupal\Deploy\Modulefetch\Config\Config')
      ->setMethods(['__construct', 'load', 'addDataSource'])
      ->disableOriginalConstructor()
      ->getMock();

    $config->save('fail', $this->test_config);
  }

  /**
   * @expectedException InvalidArgumentException
   */
  public function testSaveFailWithSource() {
    $config = $this->getMockBuilder('Dbmedialab\Drupal\Deploy\Modulefetch\Config\Config')
      ->setMethods(['__construct', 'load', 'addDataSource'])
      ->disableOriginalConstructor()
      ->getMock();

    $config->addDataSource('fail', 'non-existant');
    $config->save('fail', $this->test_config);
  }

  /**
   * @expectedException RuntimeException
   */
  public function testSaveFileIsWritable() {
    $config = $this->getMockBuilder('Dbmedialab\Drupal\Deploy\Modulefetch\Config\Config')
      ->setConstructorArgs([$this->parser, $this->dumper])
      ->setMethods(['__construct', 'load'])
      ->getMock();

    vfsStream::newFile('config', 0000)
      ->at($this->fs_root);
      //->setContent($this->dumper->dump($this->test_config));

    $config->addDataSource('config', vfsStream::url('root/config'));
    $config->save('config', $this->test_config);
  }

  public function testSave() {
    $this->markTestSkipped("https://github.com/mikey179/vfsStream/issues/44");
  }
}