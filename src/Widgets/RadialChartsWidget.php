<?php
/**
 * Freeform for Craft
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2019, Solspace, Inc.
 * @link          https://docs.solspace.com/craft/freeform
 * @license       https://docs.solspace.com/license-agreement
 */

namespace Solspace\FreeformPro\Widgets;

use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Charts\RadialChartData;
use Solspace\Freeform\Resources\Bundles\ChartJsBundle;
use Solspace\FreeformPro\FreeformPro;
use Solspace\FreeformPro\Services\WidgetsService;

class RadialChartsWidget extends AbstractWidget
{
    /** @var string */
    public $title;

    /** @var array */
    public $formIds;

    /** @var string */
    public $dateRange;

    /** @var int */
    public $chartHeight;

    /** @var string */
    public $chartType;

    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Freeform::getInstance()->name . ' ' . FreeformPro::t('Radial Chart');
    }

    /**
     * @return string
     */
    public static function iconPath(): string
    {
        return __DIR__ . '/../icon-mask.svg';
    }

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        if (null === $this->title) {
            $this->title = self::displayName();
        }

        if (null === $this->formIds) {
            $this->formIds = [];
        }

        if (null === $this->dateRange) {
            $this->dateRange = WidgetsService::RANGE_LAST_30_DAYS;
        }

        if (null === $this->chartHeight) {
            $this->chartHeight = 100;
        }

        if (null === $this->chartType) {
            $this->chartType = WidgetsService::CHART_DONUT;
        }
    }

    /**
     * @return string
     */
    public function getBodyHtml(): string
    {
        \Craft::$app->view->registerAssetBundle(ChartJsBundle::class);
        $data = $this->getChartData();

        return \Craft::$app->view->renderTemplate(
            'freeform-pro/_widgets/radial-charts/body',
            [
                'chartData' => $data,
                'settings'  => $this,
            ]
        );
    }

    /**
     * @return string
     */
    public function getSettingsHtml(): string
    {
        $forms        = $this->getFormService()->getAllForms();
        $formsOptions = [];
        foreach ($forms as $form) {
            $formsOptions[$form->id] = $form->name;
        }

        return \Craft::$app->view->renderTemplate(
            'freeform-pro/_widgets/radial-charts/settings',
            [
                'settings'         => $this,
                'formOptions'      => $formsOptions,
                'chartTypes'       => [
                    WidgetsService::CHART_PIE        => 'Pie',
                    WidgetsService::CHART_DONUT      => 'Donut',
                    WidgetsService::CHART_POLAR_AREA => 'Polar Area',
                ],
                'dateRangeOptions' => $this->getWidgetsService()->getDateRanges(),
            ]
        );
    }

    /**
     * @return RadialChartData
     * @throws \Solspace\Freeform\Library\Exceptions\FreeformException
     */
    private function getChartData(): RadialChartData
    {
        list($rangeStart, $rangeEnd) = $this->getWidgetsService()->getRange($this->dateRange);

        $forms = $this->getFormService()->getAllForms();

        $formList = [];
        if ($this->formIds === '*') {
            $formList = $forms;
        } else {
            foreach ($forms as $form) {
                if (\in_array($form->id, $this->formIds, false)) {
                    $formList[$form->id] = $form;
                }
            }
        }

        $chartData = $this->getChartsService()->getRadialFormSubmissionData($rangeStart, $rangeEnd, $formList);
        $chartData->setChartType($this->chartType);

        return $chartData;
    }
}
