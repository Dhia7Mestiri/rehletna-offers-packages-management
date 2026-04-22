<?php

namespace App\Controller;

use App\Entity\Actualite;
use App\Repository\ActualiteRepository;
use App\Repository\OfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/agency/banners')]
class ActualiteController extends AbstractController
{
    #[Route('', name: 'app_actualite_index')]
    public function index(ActualiteRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_AGENCY');

        $banners = $repo->findByAgency($this->getUser());

        return $this->render('actualite/index.html.twig', [
            'banners' => $banners,
        ]);
    }

    #[Route('/new', name: 'app_actualite_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        OfferRepository $offerRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_AGENCY');

        $errors = [];

        if ($request->isMethod('POST')) {
            $titre     = trim((string) $request->request->get('titre', ''));
            $bannerUrl = trim((string) $request->request->get('bannerUrl', ''));
            $offerId   = $request->request->get('offerId');
            $endsAt    = $request->request->get('endsAt');

            // Validate
            if (!$titre)     $errors[] = 'Title is required.';
            if (!$bannerUrl) $errors[] = 'Banner image URL is required.';
            if ($bannerUrl && !filter_var($bannerUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'Please enter a valid URL.';
            }

            if (empty($errors)) {
                $actualite = new Actualite();
                $actualite->setTitre($titre);
                $actualite->setBannerUrl($bannerUrl);
                $actualite->setAgence($this->getUser());
                $actualite->setIsActive(true);

                if ($offerId) {
                    $offer = $offerRepo->find($offerId);
                    if ($offer) $actualite->setOffer($offer);
                }

                if ($endsAt) {
                    try {
                        $actualite->setEndsAt(new \DateTimeImmutable($endsAt));
                    } catch (\Exception) {}
                }

                $em->persist($actualite);
                $em->flush();

                $this->addFlash('success', 'Banner created successfully!');
                return $this->redirectToRoute('app_actualite_index');
            }
        }

        $offers = $offerRepo->findBy(
            ['user' => $this->getUser(), 'status' => 'ACTIVE'],
            ['createdAt' => 'DESC']
        );

        return $this->render('actualite/new.html.twig', [
            'offers' => $offers,
            'errors' => $errors,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_actualite_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(int $id, ActualiteRepository $repo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_AGENCY');

        $banner = $repo->find($id);
        if (!$banner || $banner->getAgence() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        $banner->setIsActive(!$banner->isActive());
        $em->flush();

        $this->addFlash('success', 'Banner ' . ($banner->isActive() ? 'activated' : 'deactivated') . '.');
        return $this->redirectToRoute('app_actualite_index');
    }

    #[Route('/{id}/delete', name: 'app_actualite_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, ActualiteRepository $repo, EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_AGENCY');

        $banner = $repo->find($id);
        if (!$banner || $banner->getAgence() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('delete_banner_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        // Delete file
        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $banner->getBannerUrl();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $em->remove($banner);
        $em->flush();

        $this->addFlash('success', 'Banner deleted.');
        return $this->redirectToRoute('app_actualite_index');
    }

    #[Route('/{id}/click', name: 'app_actualite_click', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function click(int $id, ActualiteRepository $repo, EntityManagerInterface $em): Response
    {
        $banner = $repo->find($id);
        if ($banner) {
            $banner->setClickCount($banner->getClickCount() + 1);
            $em->flush();
        }

        return $this->json(['clicks' => $banner?->getClickCount() ?? 0]);
    }
}