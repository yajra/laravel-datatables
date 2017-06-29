<?php

namespace Yajra\Datatables\Transformers;

use League\Fractal\Resource\Collection;
use League\Fractal\Serializer\SerializerAbstract;
use League\Fractal\TransformerAbstract;

class FractalTransformer
{
    /**
     * Transform output using the given transformer and serializer.
     *
     * @param array $output
     * @param mixed $transformer
     * @param mixed $serializer
     * @return array
     */
    public function transform(array $output, $transformer, $serializer = null)
    {
        $fractal = app('datatables.fractal');

        if ($serializer !== null) {
            $fractal->setSerializer($this->createSerializer($serializer));
        }

        //Get transformer reflection
        //Firs method parameter should be data/object to transform
        $reflection = new \ReflectionMethod($transformer, 'transform');
        $parameter  = $reflection->getParameters()[0];

        //If parameter is class assuming it requires object
        //Else just pass array by default
        if ($parameter->getClass()) {
            $resource = new Collection($output, $this->createTransformer($transformer));
        } else {
            $resource = new Collection(
                $output,
                $this->createTransformer($transformer)
            );
        }

        $collection = $fractal->createData($resource)->toArray();

        return $collection['data'];
    }

    /**
     * Get or create transformer serializer instance.
     *
     * @param mixed $serializer
     * @return \League\Fractal\Serializer\SerializerAbstract
     */
    protected function createSerializer($serializer)
    {
        if ($serializer instanceof SerializerAbstract) {
            return $serializer;
        }

        return new $serializer();
    }

    /**
     * Get or create transformer instance.
     *
     * @param mixed $transformer
     * @return \League\Fractal\TransformerAbstract
     */
    protected function createTransformer($transformer)
    {
        if ($transformer instanceof TransformerAbstract) {
            return $transformer;
        }

        return new $transformer();
    }
}
