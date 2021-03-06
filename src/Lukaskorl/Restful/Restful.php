<?php namespace Lukaskorl\Restful;

use Illuminate\Config\Repository as Config;
use Lukaskorl\Restful\Facades\Response;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;

use Lukaskorl\Restful\Support\Structure;

class Restful {

    /**
     * Configuration repository.
     *
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * Response object of Laravel framework.
     *
     * @var \Lukaskorl\Restful\Facades\Response
     */
    protected $response;

    /**
     * Response code
     *
     * @var integer
     */
    protected $code = null;

    /**
     * Callback function for JSONP responses
     *
     * @var null|string
     */
    protected $params = null;

    /**
     * Format of response output
     *
     * @var string
     */
    protected $format = Response::FORMAT_JSON;

    /**
     * Constructor
     *
     * @param Config $config
     * @param Response $response
     */
    public function __construct(Config $config, Response $response)
    {
        $this->config = $config;
        $this->response = $response;
    }

    /**
     * Set the response code.
     *
     * @param $code
     * @return $this
     */
    public function code($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Set the output format to JSON
     *
     * @return $this
     */
    public function json()
    {
        $this->format = Response::FORMAT_JSON;
        return $this;
    }

    /**
     * Set the output format to JSONP
     *
     * @param string|null $callback
     * @return $this
     */
    public function jsonp($callback = null)
    {
        $this->format = Response::FORMAT_JSONP;
        $this->params = $callback;
        return $this;
    }

    /**
     * Set the output format to serialized
     *
     * @return $this
     */
    public function serialized()
    {
        $this->format = Response::FORMAT_SERIALIZED;
        return $this;
    }

    /**
     * Set the output format to php
     *
     * @return $this
     */
    public function php()
    {
        $this->format = Response::FORMAT_PHP;
        return $this;
    }

    /**
     * Set the output format to XML
     *
     * @return $this
     */
    public function xml()
    {
        $this->format = Response::FORMAT_XML;
        return $this;
    }

    /**
     * Set the output format to YAML
     *
     * @return $this
     */
    public function yaml()
    {
        $this->format = Response::FORMAT_YAML;
        return $this;
    }

    /**
     * Alias for yaml() to add syntactic sugar
     * @return $this
     */
    public function yml() { return $this->yaml(); }

    /**
     * Set the default response code.
     *
     * @param $code
     * @return $this
     */
    public function setDefaultCode($code)
    {
        if ($this->code === null) {
            $this->code = $code;
        }
        return $this;
    }

    /**
     *
     *
     * @param $entities
     * @return Response
     */
    public function collection($entities)
    {
        // Create a new template structure from configuration
        $structure = Structure::make($this->config->get('restful::structure.collection.response'));

        // Check if we need to account for pagination
        if ($entities instanceof Paginator) {

            $structure->apply('pagination.total', $entities->getTotal());
            $structure->apply('pagination.per_page', $entities->getPerPage());
            $structure->apply('pagination.current_page', $entities->getCurrentPage());
            $structure->apply('pagination.last_page', $entities->getLastPage());
            $structure->apply('pagination.from', $entities->getFrom());
            $structure->apply('pagination.to', $entities->getTo());
            $structure->payload($entities->getCollection()->toArray());

        }
        // Check if we need to convert collection to array
        elseif ($entities instanceof Collection) {

            $structure->payload($entities->toArray());

        }
        // Simply return given data as payload
        else {

            $structure->payload($entities);

        }

        // Set the response code
        $this->setDefaultCode($this->config->get('restful::structure.collection.status_code'));

        // Create the response in the given format
        return $this->respondWith($structure->get());
    }

    public function entity($entity)
    {
        // Check if entity could not be found
        if ( ! $entity) {
            return $this->missing("Not found");
        }

        // Set the default HTTP response status code
        $this->setDefaultCode($this->config->get('restful::structure.entity.status_code'));

        // Create a new template structure from configuration
        $structure = Structure::make($this->config->get('restful::structure.entity.response'));

        // Load payload into structure and respond
        return $this->respondWith($structure->payload($entity)->get());
    }

    public function created($entity)
    {
        // Set the default HTTP response status code
        $this->setDefaultCode($this->config->get('restful::structure.created.status_code'));

        // Send back a normal entity response
        return $this->entity($entity);
    }

    public function updated($entity)
    {
        // Set the default HTTP response status code
        $this->setDefaultCode($this->config->get('restful::structure.updated.status_code'));

        // Send back a normal entity response
        return $this->entity($entity);
    }

    public function deleted()
    {
        // Set the default HTTP response status code
        $this->setDefaultCode($this->config->get('restful::structure.deleted.status_code'));

        // Empty response
        return $this->respondEmpty();
    }

    public function error($message = null, $code = null)
    {
        // Load default code from database
        if ($code === null) {
            $code = $this->config->get('restful::structure.error.status_code');
        }

        // Set the HTTP response status code
        $this->setDefaultCode($code);

        // Create a new template structure from configuration
        $structure = Structure::make($this->config->get('restful::structure.error.response'));
        $structure->apply('error.status_code', $this->code);
        if ($message) $structure->apply('error.message', $message);

        return $this->respondWith($structure->clean()->get());
    }

    public function unauthorized($message = "Unauthorized")
    {
        return $this->error($message, $this->config->get('restful::structure.unauthorized.status_code'));
    }

    public function missing($message = "Not found")
    {
        return $this->error($message, $this->config->get('restful::structure.not_found.status_code'));
    }

    public function forbidden($message = "Forbidden")
    {
        return $this->error($message, $this->config->get('restful::structure.forbidden.status_code'));
    }

    public function unprocessable($message = "Unprocessable Entity")
    {
        return $this->error($message, $this->config->get('restful::structure.unprocessable.status_code'));
    }

    public function validationFailed($message = "Validation failed")
    {
        return $this->unprocessable($message);
    }

    protected function respondWith($data = null)
    {
        return $this->response->{$this->format}(
            $data,
            $this->code,
            ['Content-Type' => $this->config->get('restful::format.'.$this->format.'.mime')],
            $this->params
        );
    }

    protected function respondEmpty()
    {
        return $this->response->make(null, $this->code);
    }
} 