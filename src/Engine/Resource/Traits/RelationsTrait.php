<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Engine\Resource\Traits;

trait RelationsTrait
{
    protected $relations = [];

    public function getRelations(): array
    {
        if (\is_array($this->relations) && \count($this->relations) > 0) {
            return $this->relations;
        }
        foreach ($this->fields as $field) {
            if ($field->isRelation()) {
                $this->relations[$field->getName()] = $field->getRelation();
            }
        }

        return $this->relations;
    }
}
