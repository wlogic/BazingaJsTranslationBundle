<?php

namespace Bazinga\Bundle\JsTranslationBundle\Controller;

use Bazinga\Bundle\JsTranslationBundle\Finder\TranslationFinder;
use Bazinga\Bundle\JsTranslationBundle\Util;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Filesystem\Exception\IOException;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author William DURAND <william.durand1@gmail.com>
 */
class Controller
{
    const HTTP_CACHE_TIME = 86400;
    const GET_TRANSLATIONS_QUERY = "
        SELECT ltu.key_name as key_name, ltut.content as value FROM lexik_trans_unit ltu
        JOIN lexik_trans_unit_translations ltut ON ltut.trans_unit_id = ltu.id
        WHERE ltut.locale = :locale
        AND ltu.domain = :domain
    ";

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var TranslationFinder
     */
    private $translationFinder;

    /**
     * @var array
     */
    private $loaders = [];

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var boolean
     */
    private $debug;

    /**
     * @var string
     */
    private $localeFallback;

    /**
     * @var string
     */
    private $defaultDomain;


    /**
     * @param TranslatorInterface           $translator        The translator.
     * @param Environment                   $twig              The twig environment.
     * @param TranslationFinder             $translationFinder The translation finder.
     * @param string                        $cacheDir
     * @param boolean                       $debug
     * @param string                        $localeFallback
     * @param string                        $defaultDomain
     * @param int                           $httpCacheTime
     * @throws \InvalidArgumentException
     */
    public function __construct(
        $translator,
        Environment $twig,
        TranslationFinder $translationFinder,
        EntityManagerInterface $em,
        $cacheDir,
        $debug          = false,
        $localeFallback = '',
        $defaultDomain  = ''
    ) {
        if (!$translator instanceof TranslatorInterface && !$translator instanceof LegacyTranslatorInterface) {
            throw new \InvalidArgumentException(sprintf('Providing an instance of "%s" as translator is not supported.', get_class($translator)));
        }

        $this->translator        = $translator;
        $this->twig              = $twig;
        $this->translationFinder = $translationFinder;
        $this->em                = $em;
        $this->cacheDir          = $cacheDir;
        $this->debug             = $debug;
        $this->localeFallback    = $localeFallback;
        $this->defaultDomain     = $defaultDomain;
    }

    /**
     * Add a translation loader if it does not exist.
     *
     * @param string          $id     The loader id.
     * @param LoaderInterface $loader A translation loader.
     */
    public function addLoader($id, $loader)
    {
        if (!array_key_exists($id, $this->loaders)) {
            $this->loaders[$id] = $loader;
        }
    }

    public function getTranslationsAction(Request $request, $domain, $_format)
    {
        $locales = $this->getLocales($request);

        if (0 === count($locales)) {
            throw new NotFoundHttpException();
        }

        $cache = new ConfigCache(
            sprintf(
                '%s/%s.%s.%s',
                $this->cacheDir,
                $domain,
                implode('-', $locales),
                $_format
            ), $this->debug
        );

        if (!$cache->isFresh()) {
            $resources    = [];
            $translations = [];

            foreach ($locales as $locale) {
                $translations[$locale] = [];

                $files = $this->translationFinder->get($domain, $locale);

                $translations[$locale][$domain] = [];

                foreach ($files as $filename) {
                    [$currentDomain] = Util::extractCatalogueInformationFromFilename($filename);
                    $translations[$locale][$currentDomain] = [];

                    $extension = pathinfo($filename, \PATHINFO_EXTENSION);

                    if (isset($this->loaders[$extension])) {
                        $resources[] = new FileResource($filename);
                        $catalogue   = $this->loaders[$extension]
                            ->load($filename, $locale, $currentDomain);

                        $translations[$locale][$currentDomain] = array_replace_recursive(
                            $translations[$locale][$currentDomain],
                            $catalogue->all($currentDomain)
                        );
                    }
                }
                $translations = $this->getDatabaseTranslations($translations, $locale, $domain);
            }

            $content = $this->twig->render('@BazingaJsTranslation/getTranslations.' . $_format . '.twig', array(
                'fallback'       => $this->localeFallback,
                'defaultDomain'  => $this->defaultDomain,
                'translations'   => $translations,
                'include_config' => true,
            ));

            try {
                $cache->write($content, $resources);
            } catch (IOException $e) {
                throw new NotFoundHttpException();
            }
        }

        if (method_exists($cache, 'getPath')) {
            $cachePath = $cache->getPath();
        } else {
            $cachePath = (string) $cache;
        }

        $expirationTime = new \DateTime();
        $expirationTime->modify('+' . self::HTTP_CACHE_TIME . ' seconds');
        $response = new Response(
            file_get_contents($cachePath),
            200,
            array('Content-Type' => $request->getMimeType($_format))
        );
        $response->prepare($request);
        $response->setPublic();
        $response->setETag(md5($response->getContent()));
        $response->isNotModified($request);
        $response->setExpires($expirationTime);

        return $response;
    }

    private function getLocales(Request $request)
    {
        if (null !== $locales = $request->query->get('locales')) {
            $locales = explode(',', $locales);
        } else {
            $locales = array($request->getLocale());
        }

        $locales = array_filter($locales, function ($locale) {
            return 1 === preg_match('/^[a-z]{2,3}([-_]{1}[a-zA-Z]{2})?$/', $locale);
        });

        $locales = array_unique(array_map(function ($locale) {
            return trim($locale);
        }, $locales));

        return $locales;
    }

    private function getDatabaseTranslations($translations, $locale, $domain)
    {
        $conn = $this->em->getConnection();
        $stmt = $conn->prepare(self::GET_TRANSLATIONS_QUERY);
        $stmt->bindValue("locale", $locale);
        $stmt->bindValue("domain", $domain);
        $stmt->execute();
        $result = $stmt->fetchAll();

        foreach ($result as $value) {
            $translations[$locale][$domain][$value['key_name']] = $value['value'];
        }

        return $translations;
    }
}
