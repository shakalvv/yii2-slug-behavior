<?php

namespace Zelenin\yii\behaviors;

use dosamigos\transliterator\TransliteratorHelper;
use yii\base\Behavior;
use yii\base\DynamicModel;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class Slug extends Behavior
{
    public $source_attribute = 'name';
    public $slug_attribute = 'slug';

    public $translit = true;
    public $replacement = '-';
    public $lowercase = true;
    public $unique = true;

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'processSlug'
        ];
    }

    public function processSlug($event)
    {
        $attribute = empty($this->owner->{$this->slug_attribute})
            ? $this->source_attribute
            : $this->slug_attribute;
        is_array($attribute)
            ? $this->generateSlug($this->getAttributeComponents($attribute))
            : $this->generateSlug($this->owner->$attribute);
    }

    private function getAttributeComponents($attributeNames)
    {
        $attributes = [];
        foreach ($attributeNames as $attribute) {
            $attributes[] = ArrayHelper::getValue($this->owner, $attribute);
        }
        return implode($this->replacement, $attributes);
    }

    private function generateSlug($slug)
    {
        $slug = $this->slugify($slug);
        $this->owner->{$this->slug_attribute} = $slug;
        if ($this->unique) {
            $suffix = 1;
            while (!$this->checkUniqueSlug()) {
                $this->owner->{$this->slug_attribute} = $slug . $this->replacement . ++$suffix;
            }
        }
    }

    private function slugify($slug)
    {
        return $this->translit
            ? $this->slug(TransliteratorHelper::process($slug))
            : $this->slug($slug);
    }

    private function slug($string)
    {
        $string = preg_replace('/[^\p{L}\p{Nd}]+/u', $this->replacement, $string);
        $string = trim($string, $this->replacement);
        return $this->lowercase
            ? strtolower($string)
            : $string;
    }

    private function checkUniqueSlug()
    {
        $model = DynamicModel::validateData(
            [$this->slug_attribute => $this->owner->{$this->slug_attribute}],
            [[$this->slug_attribute, 'unique', 'targetClass' => $this->owner]]
        );
        return !$model->hasErrors($this->slug_attribute);
    }
}
