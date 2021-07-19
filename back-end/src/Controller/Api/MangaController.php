<?php

namespace App\Controller\Api;

use App\Entity\Manga;
use App\Entity\UserVolume;
use App\Repository\MangaRepository;
use App\Repository\UserRepository;
use App\Repository\UserVolumeRepository;
use App\Repository\VolumeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
* @Route("/api/v1", name="api_manga-", requirements={"id"="\d+", "mangaId"="\d+"})
*/
class MangaController extends AbstractController
{
    private $mangaRepository;
    private $volumeRepository;
    private $em;
    private $userRepository;
    private $userVolume;

    public function __construct(MangaRepository $mangaRepository, VolumeRepository $volumeRepository, UserVolumeRepository $userVolume, UserRepository $userRepository, EntityManagerInterface $em)
    {
        $this->mangaRepository = $mangaRepository;
        $this->volumeRepository = $volumeRepository;
        $this->userRepository = $userRepository;
        $this->em=$em;
        $this->userVolume = $userVolume;
    }
    /**
     * Method allowing to fetch all mangas
     * @Route("/manga", name="list", methods={"GET"})
     */
    public function list(): Response
    {
        return $this->json($this->mangaRepository->findAll(), 200, [], [
            'groups' => 'mangas'
        ]);
    }
    /**
     * Method allowing to add a manga into a user's collection
     * @Route("/user/{id}/manga", name="add", methods={"POST"})
     */
    public function add(int $id, Request $request): Response
    {
      $jsonArray = json_decode($request->getContent(), true);
      //We find the manga that was selected and its attached volumes   
      $manga = $this->mangaRepository->findOneBy(['title'=>$jsonArray['title']]);
      if(!$manga) {
        return $this->json(
            ['error' => 'Ce manga n\'existe pas'], 500
        );
      }
      $volumes = $this->volumeRepository->findSelectedVolumes($manga->getId(),$jsonArray['volumes']);
      if(!$volumes) {
        return $this->json(
            ['error' => 'Il doit y avoir au moins un volume pour ce manga'], 500
        );
      }
      //Then, for each selected volume, we create a new volumeUser relation, between the current user and the current volume
      foreach($volumes as $volume) {
        $user_volume = new UserVolume();
        $user_volume->setUser($this->userRepository->find($id));
        $user_volume->setVolume($volume);
        $this->em->persist($user_volume);
      }
      $this->em->flush();
      return $this->json("Le manga ". $manga->getTitle() . " a été ajouté à votre collection", 201);       
    }
    /**
    * @Route("/user/{id}/manga/{mangaId}", name="update", methods={"PUT|PATCH"})
    */
    public function update(int $id, int $mangaId, Request $request): Response
    {
       
        $manga = $this->mangaRepository->find($mangaId);
        if(!$manga) {
            return $this->json(
                ['error' => 'Ce manga n\'existe pas'], 500
            );
        }
        // Then we add only the volumes sent by the fetch request to the user's collection
        $jsonArray = json_decode($request->getContent(), true);
        $volumesOwned = $this->volumeRepository->findSelectedVolumes($mangaId,$jsonArray['volumes']); 
        if(!$volumesOwned) {
            return $this->json(
                ['error' => 'Il doit y avoir au moins un volume pour ce manga'], 500
            );
        }
        $user = $this->userRepository->find($id);
        // We retrieve all volumes of the selected manga
        $volumes = $manga->getVolumes();
        //We remove all volumes of the collection from the current user's collection
        foreach ($volumes as $volume) {
          $user_volume = $this->userVolume->findOneBy(['user'=>$user, 'volume'=>$volume]);
          $user_volume ? $this->em->remove($user_volume):'';
        }
        
        foreach($volumesOwned as $volumeOwned) {
          $user_volume = new UserVolume();
          $user_volume->setUser($user);
          $user_volume->setVolume($volumeOwned);
          $this->em->persist($user_volume);
        }
        $this->em->flush();
        return $this->json("Le manga ". $manga->getTitle() ." a bien été mis à jour", 200); 
    }
    /**
     * @Route("/user/{id}/manga/{mangaId}/availability", name="availability", methods={"PUT|PATCH"})
     */
    public function availability(int $id, int $mangaId, Request $request): Response
    {
        
        $manga = $this->mangaRepository->find($mangaId);
        if(!$manga) {
            return $this->json(
                ['error' => 'Ce manga n\'existe pas'], 500
            );
        }
        $user = $this->userRepository->find($id);
        //We find the manga that was selected and its attached volumes
        $volumes = $manga->getVolumes();
        // We retrieve volume numbers given as available as per request
        $jsonArray = json_decode($request->getContent(), true);
        $volumesAvailable = $this->volumeRepository->findSelectedVolumes($mangaId,$jsonArray['volumes']);
        //We loop through all volumes of the manga, if there is an entry for the current user in the userVolume table, we set its status according to whether or not it appears in the volumes available
        foreach ($volumes as $volume) {
            $user_volume = $this->userVolume->findOneBy(['user'=>$user, 'volume'=>$volume]);
            if($user_volume && in_array($volume, $volumesAvailable)) {
               $user_volume->setStatus(true);
            } elseif($user_volume && !in_array($volume, $volumesAvailable)) {
                $user_volume->setStatus(false);
            }    
        }  
        $this->em->flush();
        return $this->json("Vos disponibilités pour le manga ". $manga->getTitle() ." ont bien été mises à jour", 200);   
    }
    /**
     * @Route("/user/{id}/manga/{mangaId}", name="delete", methods={"DELETE"})
     */
    public function delete(int $id, int $mangaId): Response
    {
      
        //We find the manga that was selected and its attached volumes   
        $manga = $this->mangaRepository->find($mangaId);
        if(!$manga) {
            return $this->json(
                ['error' => 'Ce manga n\'existe pas'], 500
            );
        }
        $volumes = $manga->getVolumes();
        //We remove the current user from all volumes attached to the manga
        foreach ($volumes as $volume) {
            $user_volume = $this->userVolume->findOneBy(['user'=>$this->userRepository->find($id), 'volume'=>$volume]);
            $user_volume ? $this->em->remove($user_volume):'';
        }
        $this->em->flush();
        return $this->json("Le manga" . $manga->getTitle() ." a été supprimé de votre collection", 204);
    }
}
