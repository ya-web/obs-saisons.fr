<?php

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * Class PostRepository.
 */
class PostRepository extends ServiceEntityRepository
{
    /**
     * @var Post[]|array
     */
    private $posts;

    /**
     * @var string
     */
    private $category;

    /**
     * PostRepository constructor.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * @return $this
     */
    public function setPosts(string $category): self
    {
        $this->category = $category;

        $queryParameterSet = $this->createQueryBuilder('p')
            ->andWhere('p.category = :val')
            ->setParameter('val', $category)
        ;
        if ('event' === $category) {
            $queryOrdered = $queryParameterSet->addOrderBy('p.startDate', 'ASC')
                ->addOrderBy('p.endDate', 'ASC');
        } else {
            $queryOrdered = $queryParameterSet->orderBy('p.createdAt', 'DESC');
        }

        $this->posts = $queryOrdered->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
        // uncomment to filter outdated events
        /*if ('event' === $this->category) {
            $this->posts = array_filter($this->posts, 'self::filterOutdatedEventscallback');
        }*/

        return $this;
    }

    /**
     * @return Post[]
     */
    public function findAll(): array
    {
        return $this->posts;
    }

    public function findBySlug(string $slug): ?Post
    {
        $ret = null;
        foreach ($this->posts as $post) {
            if ($slug === $post->getSlug()) {
                $ret = $post;
                break;
            }
        }

        return $ret;
    }

    public function findById(int $id): ?Post
    {
        $ret = null;
        foreach ($this->posts as $post) {
            if ($id === $post->getId()) {
                $ret = $post;
                break;
            }
        }

        return $ret;
    }

    public function findLastFeaturedPosts(int $limit = 3): array
    {
        $lastPublishedPost[0] = reset($this->posts);
        // validate limit
        if (0 >= $limit) {
            return $lastPublishedPost;
        }

        for ($i = 1; $i < $limit; ++$i) {
            $lastPublishedPost[$i] = next($this->posts);
        }
        reset($this->posts);

        return $lastPublishedPost;
    }

    public function findNextPrevious(int $id): array
    {
        $nextPrevious = [
            'previous' => $this->findNext($id),
            'next' => $this->findPrevious($id),
        ];

        return $nextPrevious;
    }

    public function findNext(int $id): ?Post
    {
        return $this->findNextOrPreviousPost($id);
    }

    public function findPrevious(int $id): ?Post
    {
        return $this->findNextOrPreviousPost($id, -1);
    }

    private function findNextOrPreviousPost(int $id, int $direction = 1): ?Post
    {
        $matchingPost = null;
        // validate $direction
        if (!1 === abs($direction)) {
            return $matchingPost;
        }

        $referencePost = $this->findById($id);
        foreach ($this->posts as $key => $post) {
            if ($referencePost === $post) {
                // posts are sorted from the most recent (or closest start date for events) to the oldest,
                // so next post is the one before in the $this->posts, previous post is the one after in $this->posts
                $matchingPostKey = $key - (1 * $direction);
                if (!empty($this->posts[$matchingPostKey])) {
                    $matchingPost = $this->posts[$matchingPostKey];
                    reset($this->posts);
                    break;
                }
            }
        }

        return $matchingPost;
    }

    /**
     * @throws \Exception
     */
    public function filterOutdatedEventscallback(Post $post): bool
    {
        $validPost = ($post->getEndDate() >= (new \DateTime('now')));

        return $validPost;
    }
}