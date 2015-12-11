<?php
namespace FalconSearch\Services;

use webignition\RobotsTxt\File\Parser;

class ParserService
{

    /**
     * @var Parser
     */
    protected $parser;

    public function setSource($source)
    {
        $this->reset();
        $this->parser->setSource($source);
    }

    public function getSitemaps()
    {
        if (!isset($this->parser)) {
            throw new \Exception('No sorce set!');
        }

        return $this->parser->getFile()
                            ->directiveList()
                            ->filter(['field' => 'sitemap'])
                            ->get();
    }

    /**
     * @param null $parser
     * @return ParserService
     */
    public function setParser($parser)
    {
        $this->parser = $parser;

        return $this;
    }

    protected function reset()
    {
        $this->parser = new Parser();
    }

}